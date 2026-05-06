<?php

namespace App\Services\FtpSubmission;

use App\Models\CustomsCode;
use App\Models\DeclarationForm;
use App\Models\DeclarationFormItem;
use App\Models\InvoiceItem;
use App\Services\ClaudeJsonClient;
use App\Services\ItemClassifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Proposes per-item fixes for a CAPS-rejected declaration.
 *
 * Given:
 *   - the structured rejection from CapsResponseParser
 *   - the original declaration (and its items / invoice items)
 *
 * Produces a list of suggestions the UI can show to the broker:
 *
 *   [
 *     [
 *       'declaration_form_item_id' => int,
 *       'invoice_item_id'          => int|null,
 *       'line_number'              => int,
 *       'description'              => string,
 *       'current_code'             => string,
 *       'category'                 => 'tariff_not_known' | ...,
 *       'error_message'            => string,
 *       'suggestions'              => [
 *           [
 *               'code'         => '1006.301',  // exact DB row, dotted form
 *               'caps_code'    => '1006301',   // 7-digit form CAPS expects
 *               'description'  => 'Rice in the husk (paddy or rough)',
 *               'duty_rate'    => 0.05,
 *               'reasoning'    => 'string',
 *               'confidence'   => 0.0..1.0,
 *           ],
 *           ...
 *       ],
 *       'auto_apply_safe' => bool,  // true when there's exactly one high-confidence suggestion
 *     ],
 *     ...
 *   ]
 *
 * Hard rules:
 *   - NEVER invents a code. Suggestions are always exact rows from `customs_codes`.
 *   - Always returns dotted form in `code` and the CAPS-rendered form in `caps_code`.
 *   - If no suggestion can be safely produced, returns suggestions=[] and lets the
 *     broker classify manually.
 */
class CapsErrorAgent
{
    protected ClaudeJsonClient $claude;
    protected ItemClassifier $classifier;

    public function __construct(ClaudeJsonClient $claude, ItemClassifier $classifier)
    {
        $this->claude = $claude;
        $this->classifier = $classifier;
    }

    /**
     * Produce per-item suggestions.
     *
     * @param  array<string,mixed>  $parsedResponse  Output of CapsResponseParser
     * @return array<int,array<string,mixed>>
     */
    public function suggestFixes(DeclarationForm $declaration, array $parsedResponse): array
    {
        $errors = $parsedResponse['errors'] ?? [];
        if (empty($errors)) {
            return [];
        }

        $items = DeclarationFormItem::where('declaration_form_id', $declaration->id)
            ->orderBy('line_number')
            ->get()
            ->keyBy('line_number');

        if ($items->isEmpty()) {
            Log::warning('CapsErrorAgent: declaration has no items', [
                'declaration_id' => $declaration->id,
            ]);
            return [];
        }

        $countryId = $declaration->country_id;
        $suggestions = [];

        foreach ($errors as $error) {
            $item = $this->resolveItem($error, $items);
            if (!$item) {
                $suggestions[] = $this->orphanedErrorSuggestion($error);
                continue;
            }

            $suggestions[] = $this->buildItemSuggestion($error, $item, $countryId);
        }

        return $suggestions;
    }

    // ===========================================================
    // Item lookup
    // ===========================================================

    protected function resolveItem(array $error, Collection $items): ?DeclarationFormItem
    {
        $line = $error['line_number'] ?? null;
        if ($line && $items->has($line)) {
            return $items->get($line);
        }

        $tariff = $this->normalizeDigits($error['tariff_code'] ?? null);
        if ($tariff) {
            $match = $items->first(function (DeclarationFormItem $i) use ($tariff) {
                return $this->normalizeDigits($i->hs_code) === $tariff;
            });
            if ($match) {
                return $match;
            }
        }

        $desc = strtolower(trim((string) ($error['item_description'] ?? '')));
        if ($desc !== '') {
            $match = $items->first(function (DeclarationFormItem $i) use ($desc) {
                return $i->description && stripos((string) $i->description, $desc) !== false;
            });
            if ($match) {
                return $match;
            }
        }

        return null;
    }

    protected function orphanedErrorSuggestion(array $error): array
    {
        return [
            'declaration_form_item_id' => null,
            'invoice_item_id' => null,
            'line_number' => $error['line_number'],
            'description' => $error['item_description'],
            'current_code' => $error['tariff_code'],
            'category' => $error['error_code'],
            'error_message' => $error['error_message'],
            'suggestions' => [],
            'auto_apply_safe' => false,
            'note' => 'Could not match this CAPS error to a declaration line item. Manual review required.',
        ];
    }

    // ===========================================================
    // Per-item suggestion
    // ===========================================================

    protected function buildItemSuggestion(array $error, DeclarationFormItem $item, ?int $countryId): array
    {
        $invoiceItem = $item->invoice_id
            ? InvoiceItem::withoutGlobalScopes()->find($item->invoice_id)
            : null;

        $current = (string) ($item->hs_code ?? '');
        $description = (string) ($item->description ?? $error['item_description'] ?? '');

        $base = [
            'declaration_form_item_id' => $item->id,
            'invoice_item_id' => $invoiceItem?->id,
            'line_number' => $item->line_number,
            'description' => $description,
            'current_code' => $current,
            'category' => $error['error_code'],
            'error_message' => $error['error_message'],
            'suggestions' => [],
            'auto_apply_safe' => false,
        ];

        switch ($error['error_code']) {
            case CapsResponseParser::CATEGORY_TARIFF_NOT_KNOWN:
            case CapsResponseParser::CATEGORY_TAX_RATE_INCORRECT:
                $base['suggestions'] = $this->suggestTariffFixes($description, $current, $countryId);
                $base['auto_apply_safe'] = $this->isAutoApplySafe($base['suggestions']);
                if ($error['error_code'] === CapsResponseParser::CATEGORY_TAX_RATE_INCORRECT && empty($base['suggestions'])) {
                    $base['note'] = 'CAPS reports the tax rate is incorrect. Verify the CPC and the rate on customs_codes.'
                        . ' If a concessionary CPC (e.g., C420) was applied, confirm the rate matches the legacy CAPS-approved rate before resubmission.';
                }
                break;

            case CapsResponseParser::CATEGORY_FIELD_NOT_COMPLETE:
                $base['note'] = "CAPS flagged a missing field" . ($error['field'] ? " ({$error['field']})" : '')
                    . '. Open the declaration item and ensure all required fields are populated.';
                break;

            case CapsResponseParser::CATEGORY_PAYMENT_NOT_KNOWN:
            case CapsResponseParser::CATEGORY_CPC_NOT_KNOWN:
            case CapsResponseParser::CATEGORY_QUANTITY_UNITS_NOT_KNOWN:
                $base['note'] = 'CAPS does not recognize the value on the declaration. Verify the code against the country reference data and update the declaration before resubmitting.';
                break;

            case CapsResponseParser::CATEGORY_VALUE_MISMATCH:
            case CapsResponseParser::CATEGORY_HEADER_DETAIL_MISMATCH:
                $base['note'] = 'CAPS detected a value or header/detail rollup mismatch. Re-run duty calculation; this is usually fixed automatically by Fix and Resubmit.';
                break;

            case CapsResponseParser::CATEGORY_SUPPLIER_NOT_FOUND:
            case CapsResponseParser::CATEGORY_TRADER_NOT_ACTIVE:
                $base['note'] = 'Trader/supplier configuration issue. This must be resolved with the customs administrator before any resubmission will succeed.';
                break;

            default:
                $base['note'] = 'Manual review required.';
                break;
        }

        return $base;
    }

    // ===========================================================
    // Tariff suggestion engine
    // ===========================================================

    /**
     * Generate ranked tariff suggestions using the existing classifier as the
     * primary source, plus DB candidates as fallback. All returned codes are
     * exact rows from `customs_codes` — never invented.
     */
    protected function suggestTariffFixes(string $description, string $currentCode, ?int $countryId): array
    {
        $description = trim($description);
        if ($description === '') {
            return [];
        }

        $candidates = collect();

        try {
            $result = $this->classifier->classify($description, $countryId);
            if (!empty($result['success']) && !empty($result['code'])) {
                $primary = $this->lookupExactCustomsCode((string) $result['code'], $countryId);
                if ($primary) {
                    $candidates->push([
                        'row' => $primary,
                        'reasoning' => (string) ($result['reasoning'] ?? 'Selected by classifier as the most likely BVI tariff for this item.'),
                        'confidence' => $this->confidenceFromClassifier($result),
                        'source' => 'classifier',
                    ]);
                }
            }

            foreach ($result['alternates'] ?? [] as $alt) {
                $altCode = (string) ($alt['code'] ?? '');
                if ($altCode === '') {
                    continue;
                }
                $row = $this->lookupExactCustomsCode($altCode, $countryId);
                if (!$row) {
                    continue;
                }
                if ($candidates->contains(fn($c) => $c['row']->code === $row->code)) {
                    continue;
                }
                $candidates->push([
                    'row' => $row,
                    'reasoning' => (string) ($alt['reasoning'] ?? 'Classifier alternate.'),
                    'confidence' => $this->confidenceFromClassifier($alt),
                    'source' => 'classifier_alt',
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('CapsErrorAgent: classifier failed; falling back to DB candidates', [
                'error' => $e->getMessage(),
                'description' => $description,
            ]);
        }

        if ($candidates->count() < 3) {
            $dbCandidates = $this->databaseCandidates($description, $currentCode, $countryId, 5);
            foreach ($dbCandidates as $row) {
                if ($candidates->contains(fn($c) => $c['row']->code === $row->code)) {
                    continue;
                }
                $candidates->push([
                    'row' => $row,
                    'reasoning' => 'Keyword match against customs_codes.description.',
                    'confidence' => 0.4,
                    'source' => 'db_keyword',
                ]);
            }
        }

        $current = $this->normalizeDigits($currentCode);
        $candidates = $candidates->reject(function ($c) use ($current) {
            return $current && $this->normalizeDigits($c['row']->code) === $current;
        });

        return $candidates
            ->take(5)
            ->map(fn($c) => $this->formatSuggestion($c['row'], $c['reasoning'], $c['confidence']))
            ->values()
            ->all();
    }

    /**
     * Database keyword candidates. Splits the description into significant
     * words and looks for any customs_codes.description that contains them.
     */
    protected function databaseCandidates(string $description, string $currentCode, ?int $countryId, int $limit): Collection
    {
        $words = collect(preg_split('/[^a-zA-Z]+/', $description))
            ->map(fn($w) => strtolower(trim($w)))
            ->filter(fn($w) => mb_strlen($w) >= 4)
            ->unique()
            ->take(6)
            ->values();

        if ($words->isEmpty()) {
            return collect();
        }

        $query = CustomsCode::query()
            ->whereNotNull('description')
            ->where('code_level', '>=', 6) // skip chapter / heading-level rows
            ->limit($limit * 4);

        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        $query->where(function ($q) use ($words) {
            foreach ($words as $w) {
                $q->orWhere('description', 'LIKE', '%' . $w . '%');
            }
        });

        $rows = $query->get();

        $rows = $rows->sortByDesc(function ($row) use ($words) {
            $desc = strtolower((string) $row->description);
            $score = 0;
            foreach ($words as $w) {
                if (str_contains($desc, $w)) {
                    $score++;
                }
            }
            return $score;
        });

        return $rows->take($limit)->values();
    }

    protected function lookupExactCustomsCode(string $code, ?int $countryId): ?CustomsCode
    {
        $candidates = $this->codeCandidates($code);
        if (empty($candidates)) {
            return null;
        }

        $query = CustomsCode::query()->whereIn('code', $candidates);
        if ($countryId) {
            $query->where('country_id', $countryId);
        }
        return $query->first();
    }

    /**
     * Build candidate code formats for an exact DB lookup.
     * Mirrors the CAPS dual-format reality:
     *   - XXXX.YYY (true 7-digit subheading)
     *   - XXXX.YY  (6-digit heading)
     */
    protected function codeCandidates(string $code): array
    {
        $digits = preg_replace('/\D+/', '', $code) ?? '';
        $code = trim($code);
        $candidates = array_filter([$code]);

        if (strlen($digits) === 7) {
            $candidates[] = $digits;
            $candidates[] = substr($digits, 0, 4) . '.' . substr($digits, 4);
            if (substr($digits, -1) === '0') {
                $candidates[] = substr($digits, 0, 4) . '.' . substr($digits, 4, 2);
            }
        } elseif (strlen($digits) === 6) {
            $candidates[] = substr($digits, 0, 4) . '.' . substr($digits, 4);
        }

        return array_values(array_unique($candidates));
    }

    protected function formatSuggestion(CustomsCode $row, string $reasoning, float $confidence): array
    {
        $dotted = (string) $row->code;
        $digits = preg_replace('/\D+/', '', $dotted) ?? '';

        $capsCode = match (strlen($digits)) {
            7 => $digits,
            6 => $digits . '0',
            default => $digits,
        };

        return [
            'code' => $dotted,
            'caps_code' => $capsCode,
            'description' => (string) $row->description,
            'duty_rate' => $row->duty_rate !== null ? (float) $row->duty_rate : null,
            'unit_of_measurement' => $row->unit_of_measurement,
            'reasoning' => $reasoning,
            'confidence' => round(max(0.0, min(1.0, $confidence)), 2),
        ];
    }

    protected function confidenceFromClassifier(array $result): float
    {
        $raw = $result['confidence'] ?? null;
        if (is_numeric($raw)) {
            $value = (float) $raw;
            return $value > 1 ? min(1.0, $value / 100.0) : max(0.0, $value);
        }
        return 0.6;
    }

    protected function isAutoApplySafe(array $suggestions): bool
    {
        if (count($suggestions) !== 1) {
            return false;
        }
        return ($suggestions[0]['confidence'] ?? 0) >= 0.85;
    }

    protected function normalizeDigits(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        return $digits === '' ? null : $digits;
    }
}
