<?php

namespace App\Jobs;

use App\Models\LawDocument;
use App\Services\LawDocumentProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLawDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 1800; // 30 minutes

    /**
     * The law document to process.
     */
    protected LawDocument $document;

    /**
     * Create a new job instance.
     */
    public function __construct(LawDocument $document)
    {
        $this->document = $document;
    }

    /**
     * Execute the job.
     */
    public function handle(LawDocumentProcessor $processor): void
    {
        Log::info('Starting background processing of law document', [
            'document_id' => $this->document->id,
            'filename' => $this->document->original_filename,
        ]);

        try {
            // Increase memory and time limits for large documents
            ini_set('memory_limit', '1G');
            set_time_limit(1800); // 30 minutes
            
            $result = $processor->process($this->document);

            if ($result['success']) {
                Log::info('Law document processing completed successfully', [
                    'document_id' => $this->document->id,
                    'stats' => $result['stats'],
                ]);
            } else {
                Log::error('Law document processing failed', [
                    'document_id' => $this->document->id,
                    'error' => $result['error'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Law document processing exception', [
                'document_id' => $this->document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->document->markAsFailed($e->getMessage());
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Law document processing job failed', [
            'document_id' => $this->document->id,
            'error' => $exception->getMessage(),
        ]);

        $this->document->markAsFailed('Job failed: ' . $exception->getMessage());
    }
}
