<?php

namespace App\Jobs;

use App\Models\CapsImport;
use App\Services\CapsImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCapsInvoices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 1;

    public function __construct(
        public CapsImport $capsImport,
    ) {
    }

    public function handle(CapsImportService $service): void
    {
        $service->processInvoicePDFs($this->capsImport);
    }
}
