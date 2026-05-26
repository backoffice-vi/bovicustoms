<?php

namespace App\Services;

use App\Models\Country;
use App\Models\CountryDutyPolicy;
use App\Models\CustomsCode;
use App\Models\CustomsCodeRateOverride;
use App\Models\DeclarationForm;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for "what duty basis and what rate apply on date X
 * for country Y and tariff code Z".
 *
 * Two responsibilities:
 *   1. resolveBasis(): does customs duty compute on FOB or CIF?
 *   2. resolveRate(): is there a temporary override on this customs code?
 *
 * Everything that emits a CAPS T12, recalculates duty, validates a payload,
 * or shows the broker an expected number must go through this resolver.
 *
 * Results are cached per request via static memoisation keyed on
 * (country_id, ISO date, customs_code_id) so a 300-line declaration doesn't
 * issue 300 lookups.
 */
class DutyPolicyResolver
{
    /** @var array<string,string> */
    private array $basisCache = [];

    /** @var array<string,?float> */
    private array $rateOverrideCache = [];

    /**
     * Returns 'fob' or 'cif' for the given country and date.
     * Falls back to CountryDutyPolicy::DEFAULT_BASIS when no policy row matches.
     */
    public function resolveBasis(?int $countryId, ?string $date = null): string
    {
        if (!$countryId) {
            return CountryDutyPolicy::DEFAULT_BASIS;
        }

        $date = $this->normalizeDate($date);
        $key = $countryId . '|' . $date;

        if (array_key_exists($key, $this->basisCache)) {
            return $this->basisCache[$key];
        }

        // Pick the most recently activated row that covers $date. If two rows
        // overlap (which shouldn't happen but is possible), the latest
        // effective_from wins — closest to "current rule".
        $policy = CountryDutyPolicy::active()
            ->forCountry($countryId)
            ->effectiveOn($date)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        return $this->basisCache[$key] = $policy?->basis
            ?? CountryDutyPolicy::DEFAULT_BASIS;
    }

    /**
     * Resolve basis from a DeclarationForm — uses declaration_date if set,
     * otherwise today. Convenience wrapper over resolveBasis().
     */
    public function resolveBasisForDeclaration(DeclarationForm $declaration): string
    {
        $date = $declaration->declaration_date
            ? Carbon::parse($declaration->declaration_date)->toDateString()
            : null;

        return $this->resolveBasis($declaration->country_id, $date);
    }

    /**
     * Resolve the duty rate (percentage) to apply for a given customs code
     * on a given date for a given country. Returns the override rate if one
     * is in effect, otherwise the base rate from `customs_codes.duty_rate`.
     */
    public function resolveRate(
        CustomsCode $customsCode,
        ?int $countryId = null,
        ?string $date = null
    ): float {
        $countryId = $countryId ?? $customsCode->country_id;
        $date = $this->normalizeDate($date);
        $baseRate = (float) ($customsCode->duty_rate ?? 0);

        if (!$countryId) {
            return $baseRate;
        }

        $key = $countryId . '|' . $date . '|' . $customsCode->id;

        if (!array_key_exists($key, $this->rateOverrideCache)) {
            $override = CustomsCodeRateOverride::active()
                ->forCountry($countryId)
                ->forCustomsCode($customsCode->id)
                ->effectiveOn($date)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            $this->rateOverrideCache[$key] = $override
                ? (float) $override->override_rate
                : null;
        }

        return $this->rateOverrideCache[$key] ?? $baseRate;
    }

    /**
     * Pick the right tax base value (FOB or CIF) for an item based on the
     * resolved policy. Used by the T12 generator for the CUD R50 record.
     */
    public function pickDutyBaseValue(string $basis, float $fobValue, float $cifValue): float
    {
        return $basis === CountryDutyPolicy::BASIS_FOB ? $fobValue : $cifValue;
    }

    /**
     * Reset memoised caches. Call between unrelated declarations in long-
     * running processes (queue workers, batch jobs).
     */
    public function flush(): void
    {
        $this->basisCache = [];
        $this->rateOverrideCache = [];
    }

    private function normalizeDate(?string $date): string
    {
        if (!$date) {
            return Carbon::now()->toDateString();
        }
        return Carbon::parse($date)->toDateString();
    }
}
