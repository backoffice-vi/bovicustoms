<?php

namespace Tests\Feature;

use App\Services\FtpSubmission\CapsResponseParser;
use Tests\TestCase;

/**
 * Covers the validation/normalization layer of CapsResponseParser without
 * calling Claude. The Claude-driven PDF/text parsing is exercised by the
 * normalize() public entry point with hand-built raw payloads that mimic
 * the structured JSON Claude would return.
 */
class CapsResponseParserTest extends TestCase
{
    private function parser(): CapsResponseParser
    {
        return app(CapsResponseParser::class);
    }

    public function test_normalize_returns_clean_structure_for_well_formed_payload(): void
    {
        $raw = [
            'declaration_no' => '004497889',
            'overall_status' => 'rejected',
            'errors' => [
                [
                    'line_number' => 1,
                    'item_description' => 'Sesame oil 500ml',
                    'tariff_code' => '1515500',
                    'error_code' => 'tariff_not_known',
                    'error_message' => 'TARIFF NO. NOT KNOWN',
                    'field' => 'tariff',
                ],
                [
                    'line_number' => '2',
                    'item_description' => null,
                    'tariff_code' => null,
                    'error_code' => 'tax_rate_incorrect',
                    'error_message' => 'TAX RATE NOT CORRECT',
                    'field' => 'tax_rate',
                ],
            ],
        ];

        $result = $this->parser()->normalize($raw);

        $this->assertSame('004497889', $result['declaration_no']);
        $this->assertSame('rejected', $result['overall_status']);
        $this->assertCount(2, $result['errors']);
        $this->assertSame(1, $result['errors'][0]['line_number']);
        $this->assertSame(2, $result['errors'][1]['line_number'], 'Numeric strings should coerce to int.');
        $this->assertSame('tariff_not_known', $result['errors'][0]['error_code']);
        $this->assertSame('tax_rate_incorrect', $result['errors'][1]['error_code']);
        $this->assertEmpty($result['parser_warnings']);
    }

    public function test_unknown_error_codes_are_coerced_to_other_with_warning(): void
    {
        $raw = [
            'overall_status' => 'rejected',
            'errors' => [
                [
                    'line_number' => 1,
                    'error_code' => 'something_made_up',
                    'error_message' => 'Some unknown error',
                ],
            ],
        ];

        $result = $this->parser()->normalize($raw);

        $this->assertSame('other', $result['errors'][0]['error_code']);
        $this->assertNotEmpty($result['parser_warnings']);
    }

    public function test_invalid_overall_status_defaults_to_rejected(): void
    {
        $raw = [
            'overall_status' => 'gibberish',
            'errors' => [
                [
                    'error_code' => 'tariff_not_known',
                    'error_message' => 'TARIFF NO. NOT KNOWN',
                ],
            ],
        ];

        $result = $this->parser()->normalize($raw);

        $this->assertSame('rejected', $result['overall_status']);
        $this->assertNotEmpty($result['parser_warnings']);
    }

    public function test_accepted_with_errors_is_downgraded_to_partial(): void
    {
        $raw = [
            'overall_status' => 'accepted',
            'errors' => [
                [
                    'error_code' => 'tariff_not_known',
                    'error_message' => 'TARIFF NO. NOT KNOWN',
                ],
            ],
        ];

        $result = $this->parser()->normalize($raw);

        $this->assertSame('partial', $result['overall_status']);
    }

    public function test_errors_without_message_are_skipped(): void
    {
        $raw = [
            'overall_status' => 'rejected',
            'errors' => [
                ['error_code' => 'tariff_not_known', 'error_message' => 'Real error'],
                ['error_code' => 'tariff_not_known'], // missing error_message
                ['error_code' => 'tariff_not_known', 'error_message' => '   '], // empty after trim
            ],
        ];

        $result = $this->parser()->normalize($raw);

        $this->assertCount(1, $result['errors']);
        $this->assertSame('Real error', $result['errors'][0]['error_message']);
    }

    public function test_non_array_errors_field_is_handled_gracefully(): void
    {
        $raw = [
            'overall_status' => 'rejected',
            'errors' => 'not an array',
        ];

        $result = $this->parser()->normalize($raw);

        $this->assertSame([], $result['errors']);
        $this->assertNotEmpty($result['parser_warnings']);
    }

    public function test_accepted_with_no_errors_passes_through(): void
    {
        $raw = [
            'declaration_no' => '004497889',
            'overall_status' => 'accepted',
            'errors' => [],
        ];

        $result = $this->parser()->normalize($raw);

        $this->assertSame('accepted', $result['overall_status']);
        $this->assertEmpty($result['errors']);
    }
}
