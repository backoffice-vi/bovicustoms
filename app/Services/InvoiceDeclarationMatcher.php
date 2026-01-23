<?php

namespace App\Services;

use App\Models\DeclarationForm;
use App\Models\DeclarationFormItem;
use App\Models\Invoice;
use App\Models\InvoiceDeclarationMatch;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvoiceDeclarationMatcher
{
    public function __construct(
        protected ClaudeJsonClient $claude,
    ) {
    }

    /**
     * Matches an invoice's items to a declaration form's items.
     * Writes matches to invoice_declaration_matches.
     */
    public function matchInvoiceToDeclaration(Invoice $invoice, DeclarationForm $form, User $user): array
    {
        $invoiceItems = InvoiceItem::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->orderBy('line_number')
            ->get();

        $declItems = DeclarationFormItem::withoutGlobalScopes()
            ->where('declaration_form_id', $form->id)
            ->orderBy('line_number')
            ->get();

        if ($invoiceItems->isEmpty() || $declItems->isEmpty()) {
            return ['matched' => 0, 'method' => 'none'];
        }

        $pairs = $this->heuristicMatch($invoiceItems->all(), $declItems->all());

        // If too many ambiguous/unmatched, try an AI assist for remaining (bounded)
        $unmatchedInvoiceIds = array_values(array_diff(
            array_map(fn ($i) => $i->id, $invoiceItems->all()),
            array_column($pairs, 'invoice_item_id')
        ));

        if (count($unmatchedInvoiceIds) > 0 && count($unmatchedInvoiceIds) <= 15 && count($declItems) <= 30) {
            $aiPairs = $this->aiMatchRemaining(
                array_values(array_filter($invoiceItems->all(), fn ($it) => in_array($it->id, $unmatchedInvoiceIds, true))),
                $declItems->all()
            );
            foreach ($aiPairs as $p) {
                $pairs[] = $p;
            }
        }

        $pairs = $this->dedupePairs($pairs);

        $created = 0;
        DB::transaction(function () use ($pairs, $invoice, $form, $user, &$created) {
            foreach ($pairs as $pair) {
                $exists = InvoiceDeclarationMatch::withoutGlobalScopes()
                    ->where('invoice_item_id', $pair['invoice_item_id'])
                    ->where('declaration_form_item_id', $pair['declaration_form_item_id'])
                    ->exists();
                if ($exists) {
                    continue;
                }

                InvoiceDeclarationMatch::create([
                    'invoice_item_id' => $pair['invoice_item_id'],
                    'declaration_form_item_id' => $pair['declaration_form_item_id'],
                    'organization_id' => $user->organization_id,
                    'user_id' => $user->id,
                    'country_id' => $invoice->country_id,
                    'confidence' => (int) ($pair['confidence'] ?? 0),
                    'match_method' => $pair['match_method'] ?? 'heuristic',
                    'match_reason' => $pair['match_reason'] ?? null,
                ]);
                $created++;
            }
        });

        return [
            'matched' => $created,
            'method' => 'mixed',
        ];
    }

    protected function heuristicMatch(array $invoiceItems, array $declItems): array
    {
        $pairs = [];
        $usedDecl = [];

        foreach ($invoiceItems as $inv) {
            $best = null;
            foreach ($declItems as $dec) {
                if (isset($usedDecl[$dec->id])) {
                    continue;
                }

                $score = 0;
                $reason = [];

                if ($inv->sku && $dec->sku && strcasecmp($inv->sku, $dec->sku) === 0) {
                    $score += 60;
                    $reason[] = 'sku_match';
                }
                if ($inv->item_number && $dec->item_number && strcasecmp($inv->item_number, $dec->item_number) === 0) {
                    $score += 60;
                    $reason[] = 'item_number_match';
                }

                $sim = $this->tokenSimilarity($inv->description, $dec->description);
                $score += (int) round($sim * 40);
                if ($sim > 0.4) {
                    $reason[] = 'description_similarity';
                }

                // Light numeric agreement boosts
                if ($inv->quantity !== null && $dec->quantity !== null) {
                    if (abs((float) $inv->quantity - (float) $dec->quantity) < 0.0001) {
                        $score += 10;
                        $reason[] = 'quantity_match';
                    }
                }
                if ($inv->unit_price !== null && $dec->unit_price !== null) {
                    if (abs((float) $inv->unit_price - (float) $dec->unit_price) < 0.01) {
                        $score += 10;
                        $reason[] = 'unit_price_match';
                    }
                }

                if ($best === null || $score > $best['score']) {
                    $best = [
                        'score' => $score,
                        'invoice_item_id' => $inv->id,
                        'declaration_form_item_id' => $dec->id,
                        'match_reason' => implode(', ', $reason),
                    ];
                }
            }

            if ($best && $best['score'] >= 60) {
                $usedDecl[$best['declaration_form_item_id']] = true;
                $pairs[] = [
                    'invoice_item_id' => $best['invoice_item_id'],
                    'declaration_form_item_id' => $best['declaration_form_item_id'],
                    'confidence' => min(100, (int) $best['score']),
                    'match_method' => 'heuristic',
                    'match_reason' => $best['match_reason'],
                ];
            }
        }

        return $pairs;
    }

    protected function aiMatchRemaining(array $invoiceItems, array $declItems): array
    {
        $prompt = $this->buildAiMatchPrompt($invoiceItems, $declItems);
        $json = $this->claude->promptForJson($prompt, 180);

        $pairs = [];
        foreach (($json['matches'] ?? []) as $m) {
            if (!is_array($m)) continue;
            $invId = (int) ($m['invoice_item_id'] ?? 0);
            $decId = (int) ($m['declaration_form_item_id'] ?? 0);
            if (!$invId || !$decId) continue;
            $pairs[] = [
                'invoice_item_id' => $invId,
                'declaration_form_item_id' => $decId,
                'confidence' => (int) ($m['confidence'] ?? 70),
                'match_method' => 'ai',
                'match_reason' => (string) ($m['reason'] ?? 'ai_match'),
            ];
        }

        return $pairs;
    }

    protected function buildAiMatchPrompt(array $invoiceItems, array $declItems): string
    {
        $inv = array_map(function ($i) {
            return [
                'id' => $i->id,
                'sku' => $i->sku,
                'item_number' => $i->item_number,
                'description' => $i->description,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
            ];
        }, $invoiceItems);

        $dec = array_map(function ($d) {
            return [
                'id' => $d->id,
                'sku' => $d->sku,
                'item_number' => $d->item_number,
                'description' => $d->description,
                'quantity' => $d->quantity,
                'unit_price' => $d->unit_price,
                'hs_code' => $d->hs_code,
            ];
        }, $declItems);

        $invJson = json_encode($inv, JSON_PRETTY_PRINT);
        $decJson = json_encode($dec, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are matching invoice line items to trade declaration line items.

Goal: for each invoice item, find the best matching declaration item (0 or 1 match per invoice item). Prefer exact SKU/item_number matches, otherwise match by description + quantities/prices.

Return ONLY valid JSON:
{
  "matches": [
    {
      "invoice_item_id": 123,
      "declaration_form_item_id": 456,
      "confidence": 0-100,
      "reason": "short reason"
    }
  ]
}

Rules:
- Only match when you are reasonably confident.
- Do NOT reuse the same declaration_form_item_id for multiple invoice items.

INVOICE ITEMS:
{$invJson}

DECLARATION ITEMS:
{$decJson}
PROMPT;
    }

    protected function tokenSimilarity(string $a, string $b): float
    {
        $ta = $this->tokens($a);
        $tb = $this->tokens($b);

        if (empty($ta) || empty($tb)) return 0.0;

        $ia = array_intersect($ta, $tb);
        $u = array_unique(array_merge($ta, $tb));

        return count($u) > 0 ? (count($ia) / count($u)) : 0.0;
    }

    protected function tokens(string $s): array
    {
        $s = strtolower($s);
        preg_match_all('/[a-z0-9]{3,}/', $s, $m);
        $stop = ['the', 'and', 'for', 'with', 'from', 'this', 'that', 'item', 'items'];
        $t = array_values(array_diff(array_unique($m[0] ?? []), $stop));
        return array_slice($t, 0, 40);
    }

    protected function dedupePairs(array $pairs): array
    {
        $seenInv = [];
        $seenDec = [];
        $out = [];

        usort($pairs, fn ($a, $b) => ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0));

        foreach ($pairs as $p) {
            $inv = $p['invoice_item_id'];
            $dec = $p['declaration_form_item_id'];
            if (isset($seenInv[$inv]) || isset($seenDec[$dec])) continue;
            $seenInv[$inv] = true;
            $seenDec[$dec] = true;
            $out[] = $p;
        }

        return $out;
    }
}

