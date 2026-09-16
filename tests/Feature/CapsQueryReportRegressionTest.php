<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\CountryLevy;
use App\Models\DeclarationForm;
use App\Models\Invoice;
use App\Models\OrganizationSubmissionCredential;
use App\Models\Shipment;
use App\Models\User;
use App\Services\FtpSubmission\CapsT12Generator;
use App\Services\WebFormSubmission\CapsPreValidationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CapsQueryReportRegressionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_wharfage_rate_is_resolved_for_the_declaration_date(): void
    {
        $country = Country::where('code', 'VGB')->firstOrFail();

        $july = CountryLevy::getForCountry($country->id, '2026-07-03')
            ->firstWhere('levy_code', CountryLevy::CODE_WHARFAGE);
        $september = CountryLevy::getForCountry($country->id, '2026-09-16')
            ->firstWhere('levy_code', CountryLevy::CODE_WHARFAGE);

        $this->assertSame(1.0, (float) $july?->rate);
        $this->assertSame(2.0, (float) $september?->rate);
    }

    public function test_t12_header_validation_blocks_query_report_header_gaps(): void
    {
        $fields = array_fill(0, 35, '');
        $fields[0] = 'R10';
        $fields[18] = 'BILL-OF-LADING';

        $report = app(CapsPreValidationService::class)
            ->validateT12Content(implode(',', $fields));

        $this->assertContains('T12 supplier name (Box 1a) is missing.', $report['errors']);
        $this->assertContains('T12 carrier/voyage number (Box 3a) is missing.', $report['errors']);
        $this->assertContains('T12 city of direct shipment (Box 5a) is missing.', $report['errors']);
    }

    public function test_invoice_finalization_links_an_attached_shipment(): void
    {
        $invoice = Invoice::withoutGlobalScopes()->find(97);
        $shipment = Shipment::withoutGlobalScopes()->find(59);
        $user = User::withoutGlobalScopes()->find(2);

        if (!$invoice || !$shipment || !$user) {
            $this->markTestSkipped('TD 004656658 fixtures are not present.');
        }

        $invoice->shipments()->syncWithoutDetaching([$shipment->id]);
        $item = $invoice->invoiceItems()->orderBy('line_number')->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession([
                'invoice_country_id' => $invoice->country_id,
                'invoice_header' => [
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date?->toDateString(),
                    'currency' => $item->currency,
                ],
                'classifying_invoice_id' => $invoice->id,
            ])
            ->post(route('invoices.finalize'), [
                'items' => [[
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'customs_code' => $item->customs_code,
                    'duty_rate' => $item->duty_rate,
                    'customs_code_description' => $item->customs_code_description,
                    'line_number' => $item->line_number,
                ]],
            ]);

        $response->assertRedirect(route('invoices.show', $invoice));

        $declaration = DeclarationForm::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->firstOrFail();

        $this->assertSame($shipment->id, $declaration->shipment_id);
        $this->assertSame($shipment->shipper_contact_id, $declaration->shipper_contact_id);
        $this->assertSame($shipment->consignee_contact_id, $declaration->consignee_contact_id);
    }

    public function test_td_004656658_emits_date_appropriate_wha_and_cud_rates(): void
    {
        $declaration = DeclarationForm::withoutGlobalScopes()->find(68);
        if (!$declaration) {
            $this->markTestSkipped('Declaration 68 fixture is not present.');
        }

        $credentials = $this->makeCredentials($declaration);

        $julyContent = $this->generateWithRate($declaration, $credentials, '2026-07-03', 5.0);
        $this->assertRates($julyContent, 1.0, 5.0);

        $septemberContent = $this->generateWithRate($declaration, $credentials, '2026-09-16', 10.0);
        $this->assertRates($septemberContent, 2.0, 10.0);
    }

    private function generateWithRate(
        DeclarationForm $declaration,
        OrganizationSubmissionCredential $credentials,
        string $arrivalDate,
        float $rate1905002
    ): string {
        $declaration->arrival_date = Carbon::parse($arrivalDate);
        $declaration->duty_basis = $arrivalDate <= '2026-07-31' ? 'fob' : 'cif';

        $breakdown = $declaration->duty_breakdown;
        foreach ($breakdown as &$group) {
            if (($group['tariff_code'] ?? null) === '1905.002') {
                $group['duty_rate'] = $rate1905002;
            }
        }
        unset($group);
        $declaration->duty_breakdown = $breakdown;

        return app(CapsT12Generator::class)
            ->generate($declaration, $credentials)['content'];
    }

    private function assertRates(string $content, float $expectedWha, float $expectedCud1905002): void
    {
        $record = 0;
        $tariff = null;
        $wharfageRows = 0;
        $targetCudRows = 0;

        foreach (preg_split('/\r\n|\n|\r/', trim($content)) as $line) {
            $fields = str_getcsv($line);
            if (($fields[0] ?? '') === 'R30') {
                $record++;
                $tariff = $fields[2] ?? null;
            } elseif (($fields[0] ?? '') === 'R50' && ($fields[1] ?? '') === 'WHA') {
                $wharfageRows++;
                $this->assertSame($expectedWha, (float) ($fields[4] ?? -1), "Record {$record} WHA rate");
            } elseif (($fields[0] ?? '') === 'R50' && ($fields[1] ?? '') === 'CUD' && $tariff === '1905002') {
                $targetCudRows++;
                $this->assertSame($expectedCud1905002, (float) ($fields[4] ?? -1), "Record {$record} CUD rate");
            }
        }

        $this->assertSame(45, $wharfageRows);
        $this->assertSame(3, $targetCudRows);
    }

    private function makeCredentials(DeclarationForm $declaration): OrganizationSubmissionCredential
    {
        $credentials = new OrganizationSubmissionCredential([
            'organization_id' => $declaration->organization_id,
            'country_id' => $declaration->country_id,
            'credential_type' => OrganizationSubmissionCredential::TYPE_FTP,
            'trader_id' => '100184',
        ]);
        $credentials->credentials = [
            'trader_id' => '100184',
            'declarant_name' => "Nature's Way Ltd",
            'username' => 'test',
            'password' => 'test',
        ];

        return $credentials;
    }
}
