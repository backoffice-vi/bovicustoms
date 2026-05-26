<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\CountryDutyPolicy;
use App\Models\CustomsCode;
use App\Models\CustomsCodeRateOverride;
use App\Models\DeclarationForm;
use App\Services\DutyPolicyResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tests for DutyPolicyResolver — single source of truth for "what duty basis
 * and what rate apply on date X for country Y and tariff code Z".
 *
 * Covers:
 *   - Basis defaults to CIF when no policy row matches
 *   - Active policy in window wins; outside window falls back to default
 *   - Inactive rows are ignored
 *   - Open-ended (effective_until = NULL) windows
 *   - Most-recent-effective-from wins on overlap
 *   - Rate override returns base rate when no override
 *   - Rate override picks the in-window override rate
 *   - Override scoped per country and per customs_code
 *   - flush() clears memoised caches
 *   - resolveBasisForDeclaration uses declaration_date (not today)
 *   - pickDutyBaseValue selects FOB or CIF
 */
class DutyPolicyResolverTest extends TestCase
{
    use DatabaseTransactions;

    private DutyPolicyResolver $resolver;
    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new DutyPolicyResolver();
        $this->country = $this->createTestCountry();
    }

    public function test_basis_defaults_to_cif_when_no_policy(): void
    {
        $basis = $this->resolver->resolveBasis($this->country->id, '2026-04-01');

        $this->assertSame(CountryDutyPolicy::BASIS_CIF, $basis);
    }

    public function test_basis_returns_default_when_country_id_is_null(): void
    {
        $basis = $this->resolver->resolveBasis(null, '2026-05-15');

        $this->assertSame(CountryDutyPolicy::DEFAULT_BASIS, $basis);
    }

    public function test_basis_returns_active_policy_when_date_inside_window(): void
    {
        $this->createPolicy('fob', '2026-05-01', '2026-07-31');

        $this->assertSame('fob', $this->resolver->resolveBasis($this->country->id, '2026-05-01'));
        $this->assertSame('fob', $this->resolver->resolveBasis($this->country->id, '2026-06-15'));
        $this->assertSame('fob', $this->resolver->resolveBasis($this->country->id, '2026-07-31'));
    }

    public function test_basis_falls_back_to_default_when_date_outside_window(): void
    {
        $this->createPolicy('fob', '2026-05-01', '2026-07-31');

        $this->assertSame(CountryDutyPolicy::BASIS_CIF, $this->resolver->resolveBasis($this->country->id, '2026-04-30'));
        $this->assertSame(CountryDutyPolicy::BASIS_CIF, $this->resolver->resolveBasis($this->country->id, '2026-08-01'));
    }

    public function test_basis_ignores_inactive_rows(): void
    {
        $this->createPolicy('fob', '2026-05-01', '2026-07-31', ['is_active' => false]);

        $this->assertSame(CountryDutyPolicy::BASIS_CIF, $this->resolver->resolveBasis($this->country->id, '2026-06-15'));
    }

    public function test_basis_open_ended_until_is_in_effect_indefinitely(): void
    {
        $this->createPolicy('fob', '2026-01-01', null);

        $this->assertSame('fob', $this->resolver->resolveBasis($this->country->id, '2026-01-01'));
        $this->assertSame('fob', $this->resolver->resolveBasis($this->country->id, '2030-12-31'));
    }

    public function test_basis_most_recent_effective_from_wins_on_overlap(): void
    {
        $this->createPolicy('fob', '2026-01-01', '2026-12-31');
        $this->createPolicy('cif', '2026-06-01', '2026-12-31');

        // 2026-07-01 is inside both rows; the more-recently-effective row (CIF) wins.
        $this->assertSame('cif', $this->resolver->resolveBasis($this->country->id, '2026-07-01'));
        // 2026-03-01 only matches the FOB row.
        $this->assertSame('fob', $this->resolver->resolveBasis($this->country->id, '2026-03-01'));
    }

    public function test_basis_is_memoised_within_one_resolver_instance(): void
    {
        $this->createPolicy('fob', '2026-05-01', '2026-07-31');

        $first = $this->resolver->resolveBasis($this->country->id, '2026-06-15');

        // Mutate DB after first lookup; cached result should still come back.
        CountryDutyPolicy::where('country_id', $this->country->id)->update(['basis' => 'cif']);

        $second = $this->resolver->resolveBasis($this->country->id, '2026-06-15');

        $this->assertSame($first, $second);
        $this->assertSame('fob', $second);
    }

    public function test_flush_clears_basis_cache(): void
    {
        $this->createPolicy('fob', '2026-05-01', '2026-07-31');

        $this->resolver->resolveBasis($this->country->id, '2026-06-15');

        CountryDutyPolicy::where('country_id', $this->country->id)->update(['basis' => 'cif']);
        $this->resolver->flush();

        $this->assertSame('cif', $this->resolver->resolveBasis($this->country->id, '2026-06-15'));
    }

    public function test_resolve_basis_for_declaration_uses_declaration_date(): void
    {
        $this->createPolicy('fob', '2026-05-01', '2026-07-31');

        $declaration = new DeclarationForm();
        $declaration->country_id = $this->country->id;
        $declaration->declaration_date = Carbon::parse('2026-06-15');

        $this->assertSame('fob', $this->resolver->resolveBasisForDeclaration($declaration));

        $declaration->declaration_date = Carbon::parse('2026-04-01');
        $this->resolver->flush();
        $this->assertSame(CountryDutyPolicy::BASIS_CIF, $this->resolver->resolveBasisForDeclaration($declaration));
    }

    public function test_resolve_basis_for_declaration_falls_back_to_today_when_date_missing(): void
    {
        $this->createPolicy('fob', '2026-01-01', null);

        $declaration = new DeclarationForm();
        $declaration->country_id = $this->country->id;
        $declaration->declaration_date = null;

        $this->assertSame('fob', $this->resolver->resolveBasisForDeclaration($declaration));
    }

    public function test_pick_duty_base_value_returns_fob_or_cif(): void
    {
        $this->assertSame(100.0, $this->resolver->pickDutyBaseValue('fob', 100.0, 120.0));
        $this->assertSame(120.0, $this->resolver->pickDutyBaseValue('cif', 100.0, 120.0));
    }

    public function test_resolve_rate_returns_base_rate_when_no_override(): void
    {
        $code = $this->createTestCustomsCode(7.5);

        $rate = $this->resolver->resolveRate($code, $this->country->id, '2026-05-15');

        $this->assertSame(7.5, $rate);
    }

    public function test_resolve_rate_returns_override_when_in_window(): void
    {
        $code = $this->createTestCustomsCode(15.0);
        $this->createOverride($code, 5.0, '2026-05-01', '2026-07-31');

        $this->assertSame(5.0, $this->resolver->resolveRate($code, $this->country->id, '2026-05-15'));
    }

    public function test_resolve_rate_falls_back_to_base_outside_override_window(): void
    {
        $code = $this->createTestCustomsCode(15.0);
        $this->createOverride($code, 5.0, '2026-05-01', '2026-07-31');

        $this->assertSame(15.0, $this->resolver->resolveRate($code, $this->country->id, '2026-04-30'));
        $this->assertSame(15.0, $this->resolver->resolveRate($code, $this->country->id, '2026-08-01'));
    }

    public function test_resolve_rate_ignores_inactive_overrides(): void
    {
        $code = $this->createTestCustomsCode(15.0);
        $this->createOverride($code, 5.0, '2026-05-01', '2026-07-31', ['is_active' => false]);

        $this->assertSame(15.0, $this->resolver->resolveRate($code, $this->country->id, '2026-06-15'));
    }

    public function test_resolve_rate_override_is_scoped_to_country(): void
    {
        $code = $this->createTestCustomsCode(15.0);
        $this->createOverride($code, 5.0, '2026-05-01', '2026-07-31');

        $otherCountry = $this->createTestCountry('XYZ', 'Test Other');

        // Override registered for $this->country, not $otherCountry — base rate applies.
        $this->assertSame(15.0, $this->resolver->resolveRate($code, $otherCountry->id, '2026-06-15'));
        $this->assertSame(5.0, $this->resolver->resolveRate($code, $this->country->id, '2026-06-15'));
    }

    public function test_resolve_rate_most_recent_effective_from_wins_on_overlap(): void
    {
        $code = $this->createTestCustomsCode(15.0);
        $this->createOverride($code, 10.0, '2026-01-01', '2026-12-31');
        $this->createOverride($code, 5.0, '2026-06-01', '2026-12-31');

        $this->assertSame(10.0, $this->resolver->resolveRate($code, $this->country->id, '2026-03-01'));
        $this->assertSame(5.0, $this->resolver->resolveRate($code, $this->country->id, '2026-07-01'));
    }

    public function test_resolve_rate_returns_base_when_country_id_is_null(): void
    {
        $code = $this->createTestCustomsCode(7.5);

        $rate = $this->resolver->resolveRate($code, null, '2026-05-15');

        $this->assertSame(7.5, $rate);
    }

    public function test_flush_clears_rate_override_cache(): void
    {
        $code = $this->createTestCustomsCode(15.0);
        $this->createOverride($code, 5.0, '2026-05-01', '2026-07-31');

        $this->assertSame(5.0, $this->resolver->resolveRate($code, $this->country->id, '2026-06-15'));

        CustomsCodeRateOverride::where('customs_code_id', $code->id)->update(['override_rate' => 2.5]);
        $this->resolver->flush();

        $this->assertSame(2.5, $this->resolver->resolveRate($code, $this->country->id, '2026-06-15'));
    }

    private function createTestCountry(string $code = 'TST', string $name = 'Test Country'): Country
    {
        return Country::create([
            'code' => $code,
            'name' => $name,
            'currency_code' => 'USD',
            'is_active' => true,
        ]);
    }

    private function createPolicy(string $basis, string $from, ?string $until, array $overrides = []): CountryDutyPolicy
    {
        return CountryDutyPolicy::create(array_merge([
            'country_id' => $this->country->id,
            'basis' => $basis,
            'effective_from' => $from,
            'effective_until' => $until,
            'is_active' => true,
            'source' => 'test',
        ], $overrides));
    }

    private function createTestCustomsCode(float $dutyRate): CustomsCode
    {
        // Use a unique synthetic code so we don't collide with seeded BVI rows.
        return CustomsCode::create([
            'code' => '9999.' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
            'description' => 'Resolver test code',
            'duty_rate' => $dutyRate,
            'country_id' => $this->country->id,
        ]);
    }

    private function createOverride(
        CustomsCode $code,
        float $rate,
        string $from,
        ?string $until,
        array $overrides = []
    ): CustomsCodeRateOverride {
        return CustomsCodeRateOverride::create(array_merge([
            'country_id' => $this->country->id,
            'customs_code_id' => $code->id,
            'override_rate' => $rate,
            'effective_from' => $from,
            'effective_until' => $until,
            'is_active' => true,
            'source' => 'test',
        ], $overrides));
    }
}
