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
    public int $timeout = 600; // 10 minutes

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
     * Execute the job.
     */
    public function handle(ItemClassifier $itemClassifier): void
    {
        Log::info('Starting background classification of invoice items', [
            'invoice_id' => $this->invoice->id,
            'user_id' => $this->user->id,
            'item_count' => count($this->items),
        ]);

        $cacheKey = "invoice_classification_{$this->invoice->id}";

        try {
            // Update status to processing
            $this->updateProgress(0, 'processing', 'Starting classification...');

            $itemsWithClassifications = [];
            $totalItems = count($this->items);

            foreach ($this->items as $index => $item) {
                $description = $item['description'];
                
                // Update progress
                $progress = round((($index + 1) / $totalItems) * 100);
                $this->updateProgress($progress, 'processing', "Classifying item " . ($index + 1) . " of {$totalItems}...");

                // Run classification
                $classificationResult = $itemClassifier->classify($description, $this->countryId);
                
                // Find historical precedents for comparison
                $precedents = $this->findPrecedentsForItem($description, $this->countryId);
                
                $itemsWithClassifications[] = [
                    // Original item data
                    'description' => $description,
                    'quantity' => $item['quantity'] ?? null,
                    'unit_price' => $item['unit_price'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'item_number' => $item['item_number'] ?? null,
                    'line_number' => $index + 1,
                    
                    // Classification result
                    'classification' => $classificationResult,
                    
                    // Historical precedents
                    'precedents' => $precedents,
                    
                    // Determine if there's a conflict
                    'has_conflict' => $this->hasConflict($classificationResult, $precedents),
                ];

                Log::debug('Classified item', [
                    'invoice_id' => $this->invoice->id,
                    'item_index' => $index,
                    'description' => $description,
                    'code' => $classificationResult['code'] ?? null,
                    'success' => $classificationResult['success'] ?? false,
                ]);
            }

            // Store the results in cache for the user to access
            Cache::put($cacheKey, [
                'status' => 'completed',
                'progress' => 100,
                'message' => 'Classification complete!',
                'items_with_codes' => $itemsWithClassifications,
                'invoice_header' => $this->invoiceHeader,
                'country_id' => $this->countryId,
                'completed_at' => now()->toIso8601String(),
            ], now()->addHours(2));

            // Send notification to user
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

            // Update status to failed
            Cache::put($cacheKey, [
                'status' => 'failed',
                'progress' => 0,
                'message' => 'Classification failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ], now()->addHours(2));

            // Send failure notification
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
