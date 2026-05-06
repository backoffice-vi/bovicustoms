<?php

namespace App\Services\FtpSubmission;

use App\Services\ClaudeJsonClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

/**
 * Parses a CAPS rejection / query report into structured errors.
 *
 * CAPS sends back its verdict in two forms today:
 *   - PDF query reports (manually uploaded by the broker after CAPS emails them)
 *   - ETD<filename>_REP.TXT response files (future: picked up by an FTP poller)
 *
 * Whichever the source, the goal of this service is to turn the unstructured
 * response into a deterministic, validated array of per-line errors that
 * downstream components (CapsErrorAgent, FixAndResubmitService) can consume.
 *
 * Output shape (always returned, even on partial parse failures):
 *
 *   [
 *     'declaration_no'  => '004497889' | null,
 *     'overall_status'  => 'rejected' | 'partial' | 'accepted',
 *     'errors'          => [
 *         [
 *             'line_number'      => int|null,   // R30 line ref from CAPS report
 *             'item_description' => string|null,
 *             'tariff_code'      => string|null, // the code CAPS saw
 *             'error_code'       => string,      // canonical category, see CATEGORY_*
 *             'error_message'    => string,      // raw CAPS text
 *             'field'            => string|null, // e.g. 'tariff', 'cpc', 'tax_rate'
 *         ],
 *         ...
 *     ],
 *     'parser_warnings' => [string, ...],       // notes about parsing quality
 *     'raw_text'        => string|null,         // for debugging
 *   ]
 */
class CapsResponseParser
{
    public const CATEGORY_TARIFF_NOT_KNOWN = 'tariff_not_known';
    public const CATEGORY_TAX_RATE_INCORRECT = 'tax_rate_incorrect';
    public const CATEGORY_FIELD_NOT_COMPLETE = 'field_not_complete';
    public const CATEGORY_PAYMENT_NOT_KNOWN = 'payment_not_known';
    public const CATEGORY_SUPPLIER_NOT_FOUND = 'supplier_not_found';
    public const CATEGORY_TRADER_NOT_ACTIVE = 'trader_not_active';
    public const CATEGORY_QUANTITY_UNITS_NOT_KNOWN = 'quantity_units_not_known';
    public const CATEGORY_CPC_NOT_KNOWN = 'cpc_not_known';
    public const CATEGORY_VALUE_MISMATCH = 'value_mismatch';
    public const CATEGORY_HEADER_DETAIL_MISMATCH = 'header_detail_mismatch';
    public const CATEGORY_OTHER = 'other';

    protected const VALID_CATEGORIES = [
        self::CATEGORY_TARIFF_NOT_KNOWN,
        self::CATEGORY_TAX_RATE_INCORRECT,
        self::CATEGORY_FIELD_NOT_COMPLETE,
        self::CATEGORY_PAYMENT_NOT_KNOWN,
        self::CATEGORY_SUPPLIER_NOT_FOUND,
        self::CATEGORY_TRADER_NOT_ACTIVE,
        self::CATEGORY_QUANTITY_UNITS_NOT_KNOWN,
        self::CATEGORY_CPC_NOT_KNOWN,
        self::CATEGORY_VALUE_MISMATCH,
        self::CATEGORY_HEADER_DETAIL_MISMATCH,
        self::CATEGORY_OTHER,
    ];

    protected const VALID_OVERALL_STATUSES = ['accepted', 'rejected', 'partial'];

    protected ClaudeJsonClient $claude;

    public function __construct(ClaudeJsonClient $claude)
    {
        $this->claude = $claude;
    }

    /**
     * Parse a PDF query report (binary contents).
     */
    public function parsePdf(string $pdfBinary, ?string $rawTextHint = null): array
    {
        $prompt = $this->buildPdfPrompt($rawTextHint);

        try {
            $raw = $this->claude->promptForJsonWithPdf($prompt, $pdfBinary, 300, 8000);
        } catch (\Throwable $e) {
            Log::error('CapsResponseParser: Claude PDF parse failed', [
                'error' => $e->getMessage(),
            ]);
            return $this->emptyResult([], ['Claude parse failed: ' . $e->getMessage()], $rawTextHint);
        }

        return $this->validateAndNormalize($raw, $rawTextHint);
    }

    /**
     * Parse an uploaded file. Currently supports PDF; .TXT response files
     * (ETD..._REP.TXT) will be added when the FTP poller is in place.
     */
    public function parseUploadedFile(UploadedFile $file): array
    {
        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());

        if ($mime === 'application/pdf' || $ext === 'pdf') {
            return $this->parsePdf((string) file_get_contents($file->getRealPath()));
        }

        if (in_array($ext, ['txt', 'rep'], true) || str_contains($mime, 'text/')) {
            return $this->parseText((string) file_get_contents($file->getRealPath()));
        }

        return $this->emptyResult(
            [],
            ["Unsupported file type for CAPS response: {$mime} ({$ext}). Only PDF or TXT are supported."],
        );
    }

    /**
     * Parse plain text from an FTP response file (future use).
     */
    public function parseText(string $text): array
    {
        $prompt = $this->buildTextPrompt($text);

        try {
            $raw = $this->claude->promptForJson($prompt, 180, 6000);
        } catch (\Throwable $e) {
            Log::error('CapsResponseParser: Claude text parse failed', [
                'error' => $e->getMessage(),
            ]);
            return $this->emptyResult([], ['Claude parse failed: ' . $e->getMessage()], $text);
        }

        return $this->validateAndNormalize($raw, $text);
    }

    /**
     * Persist a manually-uploaded report and return its storage path. Caller
     * is expected to record the path on the WebFormSubmission via
     * markCapsRejected().
     */
    public function storeUploadedFile(UploadedFile $file, int $submissionId): string
    {
        $disk = Storage::disk('local');
        $dir = "caps-responses/{$submissionId}";
        $name = sprintf(
            '%s_%s.%s',
            now()->format('Ymd_His'),
            substr(bin2hex(random_bytes(4)), 0, 8),
            $file->getClientOriginalExtension() ?: 'pdf',
        );

        $path = $disk->putFileAs($dir, $file, $name);

        return $path;
    }

    // ===========================================================
    // Prompt construction
    // ===========================================================

    protected function buildPdfPrompt(?string $rawTextHint): string
    {
        $categories = implode(', ', self::VALID_CATEGORIES);
        $statuses = implode(', ', self::VALID_OVERALL_STATUSES);

        $hint = $rawTextHint
            ? "\n\nA partial OCR/text extraction is provided below as a hint, but the PDF is the source of truth:\n---\n"
                . mb_substr($rawTextHint, 0, 4000)
                . "\n---"
            : '';

        return <<<PROMPT
You are parsing a **CAPS** (Customs Automated Processing System, BVI) query / rejection report PDF.

Your job: extract a structured list of every per-line error CAPS flagged, plus the declaration's overall status.

## Output schema (return ONLY this JSON, no prose, no markdown fences)

{
  "declaration_no": "string or null",   // CAPS declaration / TD number, e.g. "004497889"
  "overall_status": "one of: {$statuses}",
  "errors": [
    {
      "line_number": number or null,        // R30 line / item number on the declaration (1-based)
      "item_description": "string or null", // item description as printed by CAPS
      "tariff_code": "string or null",      // tariff CAPS rejected, digits or dotted form
      "error_code": "one of: {$categories}",
      "error_message": "string",            // exact CAPS error text, trimmed
      "field": "string or null"             // affected field: tariff, tax_rate, cpc, payment_method, supplier_id, quantity_units, value, header_total, other
    }
  ]
}

## Category mapping rules

- "TARIFF NO. NOT KNOWN" / "TARIFF NOT KNOWN" → tariff_not_known, field=tariff
- "TAX RATE NOT CORRECT" / "TAX RATE INCORRECT" → tax_rate_incorrect, field=tax_rate
- "FIELD NOT COMPLETE" → field_not_complete, field=<best guess from context>
- "PAYMENT METHOD NOT KNOWN" → payment_not_known, field=payment_method
- "SUPPLIER ID NOT FOUND" → supplier_not_found, field=supplier_id
- "TRADER NOT ACTIVE" → trader_not_active, field=trader_id
- "QUANTITY UNITS NOT KNOWN" → quantity_units_not_known, field=quantity_units
- "CPC NOT KNOWN" / "CUSTOMS PROCEDURE CODE NOT KNOWN" → cpc_not_known, field=cpc
- "VALUE NOT CORRECT" / header–detail value mismatch → value_mismatch or header_detail_mismatch
- Anything else → other

## Hard rules

- Do NOT invent line numbers, tariff codes, or descriptions. If a field is not visible in the report, set it to null.
- Preserve tariff codes EXACTLY as printed (with dots if dotted, without if not).
- Use the canonical category strings above. No synonyms.
- If the report shows the declaration was accepted with no errors, return overall_status="accepted" and errors=[].
- If only some lines failed, use overall_status="partial".
- Return valid JSON only. No comments. No trailing commas.{$hint}
PROMPT;
    }

    protected function buildTextPrompt(string $text): string
    {
        $categories = implode(', ', self::VALID_CATEGORIES);
        $statuses = implode(', ', self::VALID_OVERALL_STATUSES);
        $clipped = mb_substr($text, 0, 12000);

        return <<<PROMPT
You are parsing a CAPS (BVI Customs) FTP response file.

Output JSON only matching this schema:

{
  "declaration_no": "string or null",
  "overall_status": "one of: {$statuses}",
  "errors": [
    {
      "line_number": number or null,
      "item_description": "string or null",
      "tariff_code": "string or null",
      "error_code": "one of: {$categories}",
      "error_message": "string",
      "field": "string or null"
    }
  ]
}

Same category rules and hard rules as for the PDF parser. No prose, no fences.

--- BEGIN RESPONSE FILE ---
{$clipped}
--- END RESPONSE FILE ---
PROMPT;
    }

    // ===========================================================
    // Validation / normalization
    // ===========================================================

    /**
     * Public entry point so tests (and any future callers that already have
     * raw JSON, e.g. an FTP-text parser that doesn't need Claude) can exercise
     * the validation/normalization logic without invoking Claude.
     */
    public function normalize(array $raw, ?string $rawText = null): array
    {
        return $this->validateAndNormalize($raw, $rawText);
    }

    protected function validateAndNormalize(array $raw, ?string $rawText): array
    {
        $warnings = [];

        $declarationNo = isset($raw['declaration_no']) && is_string($raw['declaration_no'])
            ? trim($raw['declaration_no']) ?: null
            : null;

        $overall = isset($raw['overall_status']) && is_string($raw['overall_status'])
            ? strtolower(trim($raw['overall_status']))
            : 'rejected';

        if (!in_array($overall, self::VALID_OVERALL_STATUSES, true)) {
            $warnings[] = "Unknown overall_status '{$overall}', defaulting to 'rejected'.";
            $overall = 'rejected';
        }

        $errors = [];
        $rawErrors = $raw['errors'] ?? [];
        if (!is_array($rawErrors)) {
            $warnings[] = "errors field was not an array; ignoring.";
            $rawErrors = [];
        }

        foreach ($rawErrors as $idx => $entry) {
            if (!is_array($entry)) {
                $warnings[] = "errors[{$idx}] not an object; skipped.";
                continue;
            }

            $errorCode = isset($entry['error_code']) && is_string($entry['error_code'])
                ? strtolower(trim($entry['error_code']))
                : self::CATEGORY_OTHER;

            if (!in_array($errorCode, self::VALID_CATEGORIES, true)) {
                $warnings[] = "errors[{$idx}].error_code '{$errorCode}' is not a known category; coerced to 'other'.";
                $errorCode = self::CATEGORY_OTHER;
            }

            $message = isset($entry['error_message']) && is_string($entry['error_message'])
                ? trim($entry['error_message'])
                : '';

            if ($message === '') {
                $warnings[] = "errors[{$idx}] has no error_message; skipped.";
                continue;
            }

            $errors[] = [
                'line_number' => $this->normalizeInt($entry['line_number'] ?? null),
                'item_description' => $this->normalizeString($entry['item_description'] ?? null),
                'tariff_code' => $this->normalizeString($entry['tariff_code'] ?? null),
                'error_code' => $errorCode,
                'error_message' => $message,
                'field' => $this->normalizeString($entry['field'] ?? null),
            ];
        }

        if ($overall === 'rejected' && empty($errors)) {
            $warnings[] = "overall_status=rejected but no errors were parsed.";
        }

        if ($overall === 'accepted' && !empty($errors)) {
            $warnings[] = "overall_status=accepted but errors were parsed; downgrading to 'partial'.";
            $overall = 'partial';
        }

        return [
            'declaration_no' => $declarationNo,
            'overall_status' => $overall,
            'errors' => $errors,
            'parser_warnings' => $warnings,
            'raw_text' => $rawText ? mb_substr($rawText, 0, 20000) : null,
        ];
    }

    protected function emptyResult(array $errors, array $warnings, ?string $raw = null): array
    {
        return [
            'declaration_no' => null,
            'overall_status' => 'rejected',
            'errors' => $errors,
            'parser_warnings' => $warnings,
            'raw_text' => $raw ? mb_substr($raw, 0, 20000) : null,
        ];
    }

    protected function normalizeString($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $s = trim((string) $value);
        return $s === '' ? null : $s;
    }

    protected function normalizeInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/-?\d+/', $value, $m)) {
            return (int) $m[0];
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        return null;
    }
}
