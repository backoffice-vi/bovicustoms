<?php

namespace App\Jobs;

use App\Models\CapsImport;
use App\Services\CapsImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCapsDownload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 1;

    public function __construct(
        public CapsImport $capsImport,
    ) {
    }

    public function handle(CapsImportService $service): void
    {
        $maxRetries = config('services.caps.max_retries', 3);

        if ($this->capsImport->retry_count >= $maxRetries) {
            $this->capsImport->markAs(CapsImport::STATUS_FAILED, 'Max retries exceeded');
            return;
        }

        $downloaded = $service->downloadTD($this->capsImport);

        if ($downloaded) {
            $service->importTD($this->capsImport->fresh());
        }
    }
}
