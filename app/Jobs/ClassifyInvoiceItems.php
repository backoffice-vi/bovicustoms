<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\User;
use App\Services\ItemClassifier;
use App\Notifications\InvoiceClassificationComplete;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ClassifyInvoiceItems implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 1800; // 30 minutes (concurrent batches are much faster)

    /**
     * The invoice to classify.
     */
    protected Invoice $invoice;

    /**
     * The user who initiated the classification.
     */
    protected User $user;

    /**
     * Items data to classify.
     */
    protected array $items;

    /**
     * Country ID for classification context.
     */
    protected int $countryId;

    /**
     * Invoice header data.
     */
    protected array $invoiceHeader;

    /**
     * Create a new job instance.
     */
    public function __construct(Invoice $invoice, User $user, array $items, int $countryId, array $invoiceHeader = [])
    {
        $this->invoice = $invoice;
        $this->user = $user;
        $this->items = $items;
        $this->countryId = $countryId;
        $this->invoiceHeader = $invoiceHeader;
    }

    /**
     * How many items to classify concurrently via Http::pool().
     */
    protected int $batchSize = 10;

    /**
     * Execute the job using concurrent batch classification.
     *
     * Items are processed in batches: context is gathered from the DB sequentially
     * (fast), then all Claude API calls in the batch fire concurrently via Http::pool().
     * This reduces wall-clock time from O(n × 40s) to roughly O(n/batch × 40s).
     */
    public function handle(ItemClassifier $itemClassifier): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $totalItems = count($this->items);

        Log::info('Starting background classification of invoice items', [
            'invoice_id' => $this->invoice->id,
            'user_id' => $this->user->id,
            'item_count' => $totalItems,
            'batch_size' => $this->batchSize,
        ]);

        $cacheKey = "invoice_classification_{$this->invoice->id}";

        try {
            $this->updateProgress(0, 'processing', 'Starting classification...');

            $itemsWithClassifications = [];
            $classifiedCount = 0;
            $apiConfig = $itemClassifier->getClaudeApiConfig();

            $batches = array_chunk($this->items, $this->batchSize, true);
            $batchCount = count($batches);

            foreach ($batches as $batchIndex => $batch) {
                $batchNum = $batchIndex + 1;
                $this->updateProgress(
                    round(($classifiedCount / $totalItems) * 100),
                    'processing',
                    "Classifying batch {$batchNum} of {$batchCount} ({$classifiedCount}/{$totalItems} items done)..."
                );

                // Phase 1: Prepare all items in this batch (sequential DB work — fast)
                $preparations = [];
                $resolvedInBatch = [];

                foreach ($batch as $originalIndex => $item) {
                    $description = $item['description'];
                    $prep = $itemClassifier->prepareForBatchClassification($description, $this->countryId);

                    if ($prep['resolved']) {
                        $resolvedInBatch[$originalIndex] = [
                            'item' => $item,
                            'result' => $prep['result'],
                        ];
                    } elseif (!empty($prep['fallback'])) {
                        // No context could be prepared — fall back to full sequential classify
                        $resolvedInBatch[$originalIndex] = [
                            'item' => $item,
                            'result' => $itemClassifier->classify($description, $this->countryId),
                        ];
                    } else {
                        $preparations[$originalIndex] = [
                            'item' => $item,
                            'prep' => $prep,
                        ];
                    }
                }

                // Phase 2: Fire concurrent Claude API calls for this batch
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

                    // Phase 3: Process responses
                    foreach ($prepKeys as $key) {
                        $response = $responses["item_{$key}"];
                        $item = $preparations[$key]['item'];
                        $prep = $preparations[$key]['prep'];

                        if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                            $content = $response->json()['content'][0]['text'] ?? '';
                            $result = $itemClassifier->processClaudeResponseForBatch($content, $prep);

                            if (!($result['success'] ?? false)) {
                                Log::debug('Batch item needs fallback', [
                                    'invoice_id' => $this->invoice->id,
                                    'description' => $item['description'],
                                ]);
                                $result = $itemClassifier->classify($item['description'], $this->countryId);
                            }

                            $claudeResults[$key] = [
                                'item' => $item,
                                'result' => $result,
                            ];
                        } else {
                            // HTTP failure — fall back to sequential classify
                            $errorMsg = $response instanceof \Illuminate\Http\Client\Response
                                ? $response->body()
                                : 'Connection error';
                            Log::warning('Batch Claude call failed, falling back', [
                                'invoice_id' => $this->invoice->id,
                                'description' => $item['description'],
                                'error' => substr($errorMsg, 0, 200),
                            ]);
                            $claudeResults[$key] = [
                                'item' => $item,
                                'result' => $itemClassifier->classify($item['description'], $this->countryId),
                            ];
                        }
                    }
                }

                // Phase 4: Merge resolved + Claude results in original order, find precedents
                $allBatchResults = $resolvedInBatch + $claudeResults;
                ksort($allBatchResults);

                foreach ($allBatchResults as $originalIndex => $entry) {
                    $item = $entry['item'];
                    $classificationResult = $entry['result'];
                    $description = $item['description'];
                    $globalIndex = $classifiedCount;

                    $precedents = $this->findPrecedentsForItem($description, $this->countryId);

                    $itemsWithClassifications[] = [
                        'description' => $description,
                        'quantity' => $item['quantity'] ?? null,
                        'unit_price' => $item['unit_price'] ?? null,
                        'sku' => $item['sku'] ?? null,
                        'item_number' => $item['item_number'] ?? null,
                        'line_number' => $globalIndex + 1,
                        'classification' => $classificationResult,
                        'precedents' => $precedents,
                        'has_conflict' => $this->hasConflict($classificationResult, $precedents),
                    ];

                    Log::debug('Classified item', [
                        'invoice_id' => $this->invoice->id,
                        'item_index' => $globalIndex,
                        'description' => $description,
                        'code' => $classificationResult['code'] ?? null,
                        'success' => $classificationResult['success'] ?? false,
                    ]);

                    $classifiedCount++;
                }
            }

            Cache::put($cacheKey, [
                'status' => 'completed',
                'progress' => 100,
                'message' => 'Classification complete!',
                'items_with_codes' => $itemsWithClassifications,
                'invoice_header' => $this->invoiceHeader,
                'country_id' => $this->countryId,
                'completed_at' => now()->toIso8601String(),
            ], now()->addHours(2));

            $this->user->notify(new InvoiceClassificationComplete(
                $this->invoice,
                count($itemsWithClassifications),
                true
            ));

            Log::info('Invoice classification completed successfully', [
                'invoice_id' => $this->invoice->id,
                'items_classified' => count($itemsWithClassifications),
            ]);

        } catch (\Exception $e) {
            Log::error('Invoice classification job failed', [
                'invoice_id' => $this->invoice->id,
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

            $this->user->notify(new InvoiceClassificationComplete(
                $this->invoice,
                0,
                false,
                $e->getMessage()
            ));

            throw $e;
        }
    }

    /**
     * Update the progress in cache.
     */
    protected function updateProgress(int $progress, string $status, string $message): void
    {
        $cacheKey = "invoice_classification_{$this->invoice->id}";
        
        Cache::put($cacheKey, [
            'status' => $status,
            'progress' => $progress,
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(2));
    }

    /**
     * Find historical classification precedents for an item.
     */
    protected function findPrecedentsForItem(string $description, int $countryId): array
    {
        // Search for similar items in declaration forms
        $precedents = \App\Models\DeclarationFormItem::query()
            ->where('country_id', $countryId)
            ->whereNotNull('hs_code')
            ->where(function ($query) use ($description) {
                // Simple keyword matching
                $words = explode(' ', strtolower($description));
                $significantWords = array_filter($words, fn($w) => strlen($w) > 3);
                
                foreach (array_slice($significantWords, 0, 5) as $word) {
                    $query->orWhereRaw('LOWER(description) LIKE ?', ["%{$word}%"]);
                }
            })
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'hs_code' => $item->hs_code,
                    'description' => $item->description,
                    'created_at' => $item->created_at?->format('M d, Y'),
                ];
            })
            ->toArray();

        return $precedents;
    }

    /**
     * Check if there's a conflict between AI classification and precedents.
     */
    protected function hasConflict(array $classification, array $precedents): bool
    {
        if (empty($precedents) || !($classification['success'] ?? false)) {
            return false;
        }

        $aiCode = $classification['code'] ?? '';
        if (empty($aiCode)) {
            return false;
        }

        // Normalize AI code (remove dots)
        $normalizedAiCode = str_replace('.', '', $aiCode);

        // Check if any precedent has a significantly different code
        foreach ($precedents as $precedent) {
            $precedentCode = str_replace('.', '', $precedent['hs_code'] ?? '');
            
            // If the first 4 digits differ, there's a conflict
            if (substr($normalizedAiCode, 0, 4) !== substr($precedentCode, 0, 4)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Invoice classification job failed permanently', [
            'invoice_id' => $this->invoice->id,
            'error' => $exception->getMessage(),
        ]);

        $cacheKey = "invoice_classification_{$this->invoice->id}";
        
        Cache::put($cacheKey, [
            'status' => 'failed',
            'progress' => 0,
            'message' => 'Classification failed after all retries',
            'error' => $exception->getMessage(),
            'failed_at' => now()->toIso8601String(),
        ], now()->addHours(2));
    }
}
