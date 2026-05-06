<?php

namespace Tests\Feature;

use App\Models\DeclarationForm;
use App\Models\OrganizationSubmissionCredential;
use App\Services\FtpSubmission\CapsT12Generator;
use Tests\TestCase;

/**
 * Regression test for the CAPS T12 header / detail rollup.
 *
 * CAPS rejects T12 files where R10 totals (freight, insurance, total payable)
 * do not equal the sum of the corresponding detail rows (R30 / R40 / R50).
 * Earlier today TD #004497889 came back with TOTAL INSURANCE NOT CORRECT and
 * a cascade of TAX AMOUNT MISMATCH messages because R10 was taken straight
 * from the declaration model while the detail rows were independently rounded
 * per-item. The generator now derives R10 from the same rounded detail rows.
 *
 * This test runs against the live data set (declaration 63 is the canonical
 * fixture) and asserts the invariants that CAPS validates.
 */
class CapsT12HeaderRollupTest extends TestCase
{
    public function test_t12_header_totals_equal_sum_of_detail_rows(): void
    {
        $declaration = DeclarationForm::withoutGlobalScopes()->find(63);
        if (! $declaration) {
            $this->markTestSkipped('Declaration 63 fixture not present in this environment.');
        }

        $credentials = OrganizationSubmissionCredential::forFtp(
            $declaration->organization_id,
            $declaration->country_id
        )->first();
        if (! $credentials) {
            $this->markTestSkipped('No FTP credentials seeded for declaration 63.');
        }

        $result = app(CapsT12Generator::class)->generate($declaration, $credentials);
        $rollup = $this->parseRollup($result['content']);

        $this->assertSame(
            $rollup['header']['freight'],
            $rollup['details']['freight'],
            'R10 freight total must equal sum of R40 FRT lines.'
        );
        $this->assertSame(
            $rollup['header']['insurance'],
            $rollup['details']['insurance'],
            'R10 insurance total must equal sum of R40 INS lines.'
        );
        $this->assertSame(
            $rollup['header']['total_payable'],
            $rollup['details']['r50_amount'],
            'R10 total payable must equal sum of R50 tax amounts.'
        );
        $this->assertSame(
            $rollup['header']['total_payable'],
            $rollup['details']['r30_total_due'],
            'R10 total payable must equal sum of R30 total-due fields.'
        );
    }

    /**
     * Parse a generated T12 file into header totals and per-record sums.
     * Values are kept as 2-decimal strings to match the on-the-wire format
     * the test is asserting (rounding-equivalent floats are not enough).
     */
    private function parseRollup(string $content): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $header = [];
        $sumFrt = 0.0;
        $sumIns = 0.0;
        $sumR50 = 0.0;
        $sumR30Due = 0.0;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $fields = str_getcsv($line);
            $type = $fields[0] ?? '';

            if ($type === 'R10') {
                $header = [
                    'freight' => $this->fmt2($fields[23] ?? 0),
                    'insurance' => $this->fmt2($fields[25] ?? 0),
                    'total_payable' => $this->fmt2($fields[27] ?? 0),
                ];
            } elseif ($type === 'R30') {
                $sumR30Due += (float) ($fields[12] ?? 0);
            } elseif ($type === 'R40') {
                $code = strtoupper(trim((string) ($fields[1] ?? '')));
                $amount = (float) ($fields[2] ?? 0);
                if ($code === 'FRT') {
                    $sumFrt += $amount;
                } elseif ($code === 'INS') {
                    $sumIns += $amount;
                }
            } elseif ($type === 'R50') {
                $sumR50 += (float) ($fields[5] ?? 0);
            }
        }

        return [
            'header' => $header,
            'details' => [
                'freight' => $this->fmt2($sumFrt),
                'insurance' => $this->fmt2($sumIns),
                'r50_amount' => $this->fmt2($sumR50),
                'r30_total_due' => $this->fmt2($sumR30Due),
            ],
        ];
    }

    private function fmt2(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
