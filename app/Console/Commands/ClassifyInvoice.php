<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\ItemClassifier;
use App\Notifications\InvoiceClassificationComplete;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClassifyInvoice extends Command
{
    protected $signature = 'classify:invoice {invoice_id} {user_id} {country_id}';

    protected $description = 'Run invoice item classification in a background process with progress tracking';

    public function handle(ItemClassifier $itemClassifier): int
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $invoiceId = (int) $this->argument('invoice_id');
        $userId = (int) $this->argument('user_id');
        $countryId = (int) $this->argument('country_id');

        $invoice = Invoice::withoutGlobalScopes()->find($invoiceId);
        if (!$invoice) {
            $this->error("Invoice {$invoiceId} not found.");
            return 1;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error("User {$userId} not found.");
            return 1;
        }

        $cacheKey = "invoice_classification_{$invoiceId}";

        $pendingPayload = Cache::get("pending_classification_{$invoiceId}");
        if (!$pendingPayload) {
            $this->error("No pending classification payload found in cache for invoice {$invoiceId}.");
            Cache::put($cacheKey, [
                'status' => 'failed',
                'progress' => 0,
                'message' => 'Classification payload expired. Please retry.',
                'failed_at' => now()->toIso8601String(),
            ], now()->addHours(2));
            $invoice->update(['status' => 'draft']);
            return 1;
        }

        $items = $pendingPayload['items'];
        $invoiceHeader = $pendingPayload['invoice_header'] ?? [];
        $retryOnlyFailed = $pendingPayload['retry_only_failed'] ?? false;
        $totalItems = count($items);
        $batchSize = 10;

        Cache::forget("pending_classification_{$invoiceId}");

        Log::info('Starting background classification (artisan command)', [
            'invoice_id' => $invoiceId,
            'user_id' => $userId,
            'item_count' => $totalItems,
            'batch_size' => $batchSize,
            'retry_only_failed' => $retryOnlyFailed,
        ]);

        $this->info("Classifying {$totalItems} items for invoice {$invoice->invoice_number}" . ($retryOnlyFailed ? ' (failed items only)' : '') . '...');

        try {
            $this->updateProgress($invoiceId, 0, 'processing', 'Expanding abbreviated descriptions...');

            // Phase 0: Expand all abbreviated descriptions in a single Claude call
            $rawDescriptions = array_column($items, 'description');
            $expandedMap = $itemClassifier->expandDescriptions($rawDescriptions);

            if (!empty($expandedMap)) {
                $this->info("Expanded " . count($expandedMap) . "/{$totalItems} descriptions.");
                Log::info('Description expansion results', [
                    'invoice_id' => $invoiceId,
                    'expanded_count' => count($expandedMap),
                    'sample' => array_slice($expandedMap, 0, 3, true),
                ]);
            } else {
                $this->warn("Description expansion returned no results — proceeding with raw descriptions.");
            }

            $this->updateProgress($invoiceId, 2, 'processing', 'Starting classification...');

            $itemsWithClassifications = [];
            $classifiedCount = 0;
            $apiConfig = $itemClassifier->getClaudeApiConfig();

            $batches = array_chunk($items, $batchSize, true);
            $batchCount = count($batches);

            foreach ($batches as $batchIndex => $batch) {
                $batchNum = $batchIndex + 1;
                $this->updateProgress(
                    $invoiceId,
                    round(($classifiedCount / $totalItems) * 100),
                    'processing',
                    "Classifying batch {$batchNum} of {$batchCount} ({$classifiedCount}/{$totalItems} items done)..."
                );

                $this->info("[Batch {$batchNum}/{$batchCount}] Preparing {$batchSize} items...");

                // Phase 1: Prepare all items (sequential DB work)
                // Use expanded descriptions for preparation when available
                $preparations = [];
                $resolvedInBatch = [];

                foreach ($batch as $originalIndex => $item) {
                    $description = $item['description'];
                    $expanded = $expandedMap[$originalIndex] ?? '';
                    $prepDescription = $expanded ?: $description;
                    $prep = $itemClassifier->prepareForBatchClassification($prepDescription, $countryId);

                    if ($prep['resolved']) {
                        $resolvedInBatch[$originalIndex] = [
                            'item' => $item,
                            'result' => $prep['result'],
                        ];
                    } elseif (!empty($prep['fallback'])) {
                        // Tier 1-3 pipeline couldn't prepare — go direct to Claude
                        $resolvedInBatch[$originalIndex] = [
                            'item' => $item,
                            'result' => $itemClassifier->classifyDirectWithClaude($description, $expanded),
                        ];
                    } else {
                        $preparations[$originalIndex] = [
                            'item' => $item,
                            'prep' => $prep,
                            'expanded' => $expanded,
                        ];
                    }
                }

                // Phase 2: Fire concurrent Claude API calls
                $claudeResults = [];
                if (!empty($preparations)) {
                    $prepKeys = array_keys($preparations);

                    $responses = Http::pool(function ($pool) use ($preparations, $prepKeys, $apiConfig) {
                        foreach ($prepKeys as $key) {
                            $prompt = $preparations[$key]['prep']['prompt'];
                            $pool->as("item_{$key}")
                                ->withoutVerifying()
                                ->withHeaders($apiConfig['headers'])
                                ->timeout(120)
                                ->post('https://api.anthropic.com/v1/messages', [
                                    'model' => $apiConfig['model'],
                                    'max_tokens' => $apiConfig['max_tokens'],
                                    'messages' => [
                                        ['role' => 'user', 'content' => $prompt],
                                    ],
                                ]);
                        }
                    });

                    // Phase 3: Process responses — use direct Claude fallback instead of repeating the pipeline
                    foreach ($prepKeys as $key) {
                        $response = $responses["item_{$key}"];
                        $item = $preparations[$key]['item'];
                        $prep = $preparations[$key]['prep'];
                        $expanded = $preparations[$key]['expanded'] ?? '';

                        if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                            $content = $response->json()['content'][0]['text'] ?? '';
                            $result = $itemClassifier->processClaudeResponseForBatch($content, $prep);

                            if (!($result['success'] ?? false)) {
                                $this->line("    Batch mode failed for: {$item['description']} — falling back to direct Claude classification");
                                $result = $itemClassifier->classifyDirectWithClaude($item['description'], $expanded);
                            }

                            $claudeResults[$key] = ['item' => $item, 'result' => $result];
                        } else {
                            $this->line("    API error for: {$item['description']} — falling back to direct Claude classification");
                            $claudeResults[$key] = [
                                'item' => $item,
                                'result' => $itemClassifier->classifyDirectWithClaude($item['description'], $expanded),
                            ];
                        }
                    }
                }

                // Phase 4: Merge and store results
                $allBatchResults = $resolvedInBatch + $claudeResults;
                ksort($allBatchResults);

                foreach ($allBatchResults as $entry) {
                    $item = $entry['item'];
                    $classificationResult = $entry['result'];
                    $description = $item['description'];
                    $globalIndex = $classifiedCount;

                    $precedents = $this->findPrecedentsForItem($description, $countryId);

                    $itemsWithClassifications[] = [
                        'description' => $description,
                        'quantity' => $item['quantity'] ?? null,
                        'unit_price' => $item['unit_price'] ?? null,
                        'sku' => $item['sku'] ?? null,
                        'item_number' => $item['item_number'] ?? null,
                        'line_number' => $item['line_number'] ?? ($globalIndex + 1),
                        'classification' => $classificationResult,
                        'precedents' => $precedents,
                        'has_conflict' => $this->hasConflict($classificationResult, $precedents),
                    ];

                    $code = $classificationResult['code'] ?? 'N/A';
                    $ok = ($classificationResult['success'] ?? false) ? 'OK' : 'FAIL';
                    $this->line("  [{$ok}] #{$globalIndex}: {$description} -> {$code}");

                    Log::debug('Classified item', [
                        'invoice_id' => $invoiceId,
                        'item_index' => $globalIndex,
                        'description' => $description,
                        'code' => $classificationResult['code'] ?? null,
                        'success' => $classificationResult['success'] ?? false,
                    ]);

                    $classifiedCount++;
                }
            }

            // Save classification results to the database
            $this->saveClassificationResults($invoice, $user, $countryId, $itemsWithClassifications, $retryOnlyFailed);

            Cache::put($cacheKey, [
                'status' => 'completed',
                'progress' => 100,
                'message' => 'Classification complete!',
                'items_with_codes' => $itemsWithClassifications,
                'invoice_header' => $invoiceHeader,
                'country_id' => $countryId,
                'completed_at' => now()->toIso8601String(),
            ], now()->addHours(2));

            if ($retryOnlyFailed) {
                // Check all items across the whole invoice, not just the retried batch
                $unclassifiedCount = InvoiceItem::withoutGlobalScopes()
                    ->where('invoice_id', $invoice->id)
                    ->whereNull('customs_code')
                    ->count();
                $allClassified = $unclassifiedCount === 0;
            } else {
                $successCount = collect($itemsWithClassifications)
                    ->filter(fn($i) => !empty($i['classification']['code']))
                    ->count();
                $allClassified = $successCount === $totalItems;
            }

            $invoice->update([
                'status' => $allClassified ? 'classified' : 'partially_classified',
            ]);

            $user->notify(new InvoiceClassificationComplete(
                $invoice,
                count($itemsWithClassifications),
                true
            ));

            Log::info('Invoice classification completed successfully (artisan)', [
                'invoice_id' => $invoiceId,
                'items_classified' => count($itemsWithClassifications),
            ]);

            $this->info("Done: {$classifiedCount} items classified.");
            return 0;

        } catch (\Exception $e) {
            Log::error('Invoice classification command failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Cache::put($cacheKey, [
                'status' => 'failed',
                'progress' => 0,
                'message' => 'Classification failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ], now()->addHours(2));

            $invoice->update(['status' => 'draft']);

            $user->notify(new InvoiceClassificationComplete($invoice, 0, false, $e->getMessage()));

            $this->error("Classification failed: " . $e->getMessage());
            return 1;
        }
    }

    protected function updateProgress(int $invoiceId, int $progress, string $status, string $message): void
    {
        Cache::put("invoice_classification_{$invoiceId}", [
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(2));
    }

    protected function findPrecedentsForItem(string $description, int $countryId): array
    {
        return \App\Models\DeclarationFormItem::query()
            ->where('country_id', $countryId)
            ->whereNotNull('hs_code')
            ->where(function ($query) use ($description) {
                $words = explode(' ', strtolower($description));
                $significantWords = array_filter($words, fn($w) => strlen($w) > 3);
                foreach (array_slice($significantWords, 0, 5) as $word) {
                    $query->orWhereRaw('LOWER(description) LIKE ?', ["%{$word}%"]);
                }
            })
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'hs_code' => $item->hs_code,
                'description' => $item->description,
                'created_at' => $item->created_at?->format('M d, Y'),
            ])
            ->toArray();
    }

    protected function hasConflict(array $classification, array $precedents): bool
    {
        if (empty($precedents) || !($classification['success'] ?? false)) {
            return false;
        }
        $aiCode = $classification['code'] ?? '';
        if (empty($aiCode)) {
            return false;
        }
        $normalizedAiCode = str_replace('.', '', $aiCode);
        foreach ($precedents as $precedent) {
            $precedentCode = str_replace('.', '', $precedent['hs_code'] ?? '');
            if (substr($normalizedAiCode, 0, 4) !== substr($precedentCode, 0, 4)) {
                return true;
            }
        }
        return false;
    }

    protected function saveClassificationResults(Invoice $invoice, User $user, int $countryId, array $itemsWithClassifications, bool $retryOnlyFailed = false): void
    {
        if ($retryOnlyFailed) {
            // Merge: only update items that were retried, keep existing classified items intact
            $retriedByLine = collect($itemsWithClassifications)->keyBy('line_number');

            Log::info('Retry save: matching by line_number', [
                'invoice_id' => $invoice->id,
                'retried_line_numbers' => $retriedByLine->keys()->toArray(),
                'retried_count' => $retriedByLine->count(),
            ]);

            $existingItems = InvoiceItem::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->get();

            $updatedCount = 0;
            foreach ($existingItems as $existing) {
                $retried = $retriedByLine->get($existing->line_number);
                if ($retried) {
                    $c = $retried['classification'] ?? [];
                    $code = $c['code'] ?? null;
                    $existing->update([
                        'customs_code' => $code,
                        'duty_rate' => $c['duty_rate'] ?? null,
                        'customs_code_description' => $c['description'] ?? null,
                    ]);
                    $updatedCount++;
                    Log::debug("Retry save: updated line {$existing->line_number} -> {$code}");
                }
            }

            Log::info("Retry save: matched and updated {$updatedCount} of {$retriedByLine->count()} retried items");

            // Update the invoice items JSON to reflect new codes
            $allItems = InvoiceItem::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->orderBy('line_number')
                ->get();

            $updatedJson = $allItems->map(fn($item) => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'sku' => $item->sku,
                'item_number' => $item->item_number,
                'line_number' => $item->line_number,
                'customs_code' => $item->customs_code,
                'duty_rate' => $item->duty_rate,
                'customs_code_description' => $item->customs_code_description,
            ])->toArray();

            $invoice->update(['items' => $updatedJson]);
            $this->info("Updated {$retriedByLine->count()} retried items (kept existing classifications intact).");
        } else {
            // Full replacement
            $updatedItems = [];
            foreach ($itemsWithClassifications as $item) {
                $classification = $item['classification'] ?? [];
                $updatedItems[] = [
                    'description' => $item['description'],
                    'quantity' => $item['quantity'] ?? null,
                    'unit_price' => $item['unit_price'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'item_number' => $item['item_number'] ?? null,
                    'line_number' => $item['line_number'] ?? null,
                    'customs_code' => $classification['code'] ?? null,
                    'duty_rate' => $classification['duty_rate'] ?? null,
                    'customs_code_description' => $classification['description'] ?? null,
                ];
            }

            $invoice->update(['items' => $updatedItems]);

            InvoiceItem::where('invoice_id', $invoice->id)->delete();

            foreach ($itemsWithClassifications as $index => $item) {
                $classification = $item['classification'] ?? [];
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'organization_id' => $invoice->organization_id,
                    'user_id' => $user->id,
                    'country_id' => $countryId,
                    'line_number' => $item['line_number'] ?? ($index + 1),
                    'sku' => $item['sku'] ?? null,
                    'item_number' => $item['item_number'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'] ?? null,
                    'unit_price' => $item['unit_price'] ?? null,
                    'line_total' => ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0),
                    'currency' => 'USD',
                    'customs_code' => $classification['code'] ?? null,
                    'duty_rate' => $classification['duty_rate'] ?? null,
                    'customs_code_description' => $classification['description'] ?? null,
                ]);
            }

            $this->info("Saved classification results: " . count($itemsWithClassifications) . " invoice items created.");
        }
    }
}
