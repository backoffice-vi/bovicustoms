<?php

namespace App\Services;

use App\Models\CapsImport;
use App\Models\Country;
use App\Models\DeclarationForm;
use App\Models\DeclarationFormItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Organization;
use App\Models\OrganizationSubmissionCredential;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class CapsImportService
{
    public function __construct(
        protected InvoiceDocumentExtractor $invoiceExtractor,
        protected InvoiceDeclarationMatcher $matcher,
        protected CapsImportErrorRecoveryService $errorRecovery,
    ) {
    }

    /**
     * Fetch TD numbers from CAPS and create tracking records for each.
     * Also detects already-downloaded TDs on disk.
     */
    public function fetchTDList(User $user, int $countryId): array
    {
        $downloadsDir = storage_path('app/caps-downloads');
        if (!File::isDirectory($downloadsDir)) {
            File::makeDirectory($downloadsDir, 0755, true);
        }

        $timeout = config('services.caps.download_timeout', 60);
        $creds = $this->resolveCapsCredentials($user->organization_id, $countryId);

        $process = new Process(
            $this->buildPlaywrightArgs($creds, ['--all', '--list-only']),
            base_path(), null, null, $timeout + 30
        );

        $process->run();

        $tdNumbers = [];

        if ($process->isSuccessful()) {
            $output = $process->getOutput();
            $json = json_decode($output, true);
            $tdNumbers = $json['td_numbers'] ?? [];
        }

        // Fallback: if --list-only not supported, scan the CAPS list via the existing downloader
        // or scan existing downloaded folders
        if (empty($tdNumbers)) {
            $tdNumbers = $this->scanDownloadedFolders();
        }

        // If still empty, try running the full downloader just for listing
        if (empty($tdNumbers)) {
            $tdNumbers = $this->fetchTDNumbersViaPlaywright($timeout);
        }

        $created = 0;
        $existing = 0;
        $orgId = $user->organization_id;

        foreach ($tdNumbers as $tdNumber) {
            $import = CapsImport::where('organization_id', $orgId)
                ->where('td_number', $tdNumber)
                ->first();

            if ($import) {
                $existing++;
                continue;
            }

            $import = CapsImport::create([
                'user_id' => $user->id,
                'organization_id' => $orgId,
                'country_id' => $countryId,
                'td_number' => $tdNumber,
                'status' => CapsImport::STATUS_PENDING,
            ]);

            // Check if already downloaded on disk
            $tdDir = $downloadsDir . '/' . $tdNumber;
            if (File::exists($tdDir . '/data.json')) {
                $this->syncFromDisk($import, $tdDir);
            }

            $created++;
        }

        return [
            'total' => count($tdNumbers),
            'created' => $created,
            'existing' => $existing,
            'td_numbers' => $tdNumbers,
        ];
    }

    /**
     * Sync a CapsImport record from already-downloaded disk data.
     */
    public function syncFromDisk(CapsImport $import, ?string $tdDir = null): void
    {
        $tdDir = $tdDir ?: storage_path('app/caps-downloads/' . $import->td_number);

        if (!File::exists($tdDir . '/data.json')) {
            return;
        }

        $data = json_decode(File::get($tdDir . '/data.json'), true);
        $attachments = [];

        $attachDir = $tdDir . '/attachments';
        if (File::isDirectory($attachDir)) {
            foreach (File::files($attachDir) as $file) {
                $attachments[] = $file->getFilename();
            }
        }

        $import->update([
            'status' => CapsImport::STATUS_DOWNLOADED,
            'caps_data' => $data,
            'attachments' => $attachments,
            'items_count' => count($data['items'] ?? []),
            'download_path' => $tdDir,
            'downloaded_at' => now(),
        ]);

        // Check if already imported into legacy clearances
        $existingForm = DeclarationForm::withoutGlobalScopes()
            ->where('form_number', $import->td_number)
            ->where('source_type', 'legacy')
            ->first();

        if ($existingForm) {
            $import->update([
                'status' => CapsImport::STATUS_IMPORTED,
                'declaration_form_id' => $existingForm->id,
                'shipment_id' => $existingForm->shipment_id,
                'imported_at' => $existingForm->created_at,
            ]);
        }
    }

    /**
     * Download a single TD from CAPS using the Playwright script.
     */
    public function downloadTD(CapsImport $import): bool
    {
        $import->markAs(CapsImport::STATUS_DOWNLOADING);

        $timeout = config('services.caps.download_timeout', 60);
        $creds = $this->resolveCapsCredentials($import->organization_id, $import->country_id);

        $process = new Process(
            $this->buildPlaywrightArgs($creds, ['--td=' . $import->td_number]),
            base_path(), null, null, ($timeout * 2) + 30
        );

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                $error = $process->getErrorOutput() ?: $process->getOutput();
                $errorMsg = mb_substr($error, 0, 2000);

                $import->update([
                    'status' => CapsImport::STATUS_FAILED,
                    'error_message' => $errorMsg,
                    'retry_count' => $import->retry_count + 1,
                ]);

                $this->analyzeAndStoreDiagnosis($import, $errorMsg, 'download', [
                    'process_output' => mb_substr($process->getOutput(), -1500),
                ]);

                return false;
            }

            $tdDir = storage_path('app/caps-downloads/' . $import->td_number);
            if (!File::exists($tdDir . '/data.json')) {
                $errorMsg = 'Download completed but no data.json found';
                $import->update([
                    'status' => CapsImport::STATUS_FAILED,
                    'error_message' => $errorMsg,
                    'retry_count' => $import->retry_count + 1,
                ]);

                $this->analyzeAndStoreDiagnosis($import, $errorMsg, 'download', [
                    'process_output' => mb_substr($process->getOutput(), -1500),
                ]);

                return false;
            }

            $this->syncFromDisk($import, $tdDir);
            return true;

        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            $import->update([
                'status' => CapsImport::STATUS_FAILED,
                'error_message' => $errorMsg,
                'retry_count' => $import->retry_count + 1,
            ]);

            $this->analyzeAndStoreDiagnosis($import, $errorMsg, 'download');

            return false;
        }
    }

    /**
     * Import a downloaded TD into the legacy clearances system.
     * Reuses the logic from ImportCapsHistory artisan command.
     */
    public function importTD(CapsImport $import): bool
    {
        if (!$import->caps_data) {
            $import->markAs(CapsImport::STATUS_FAILED, 'No scraped data available');
            return false;
        }

        $existingForm = DeclarationForm::withoutGlobalScopes()
            ->where('form_number', $import->td_number)
            ->where('source_type', 'legacy')
            ->first();

        if ($existingForm) {
            $import->update([
                'status' => CapsImport::STATUS_IMPORTED,
                'declaration_form_id' => $existingForm->id,
                'shipment_id' => $existingForm->shipment_id,
                'imported_at' => $existingForm->created_at,
            ]);
            return true;
        }

        $import->markAs(CapsImport::STATUS_IMPORTING);

        try {
            $data = $import->caps_data;
            $header = $data['header'] ?? [];
            $items = $data['items'] ?? [];
            $user = $import->user;
            $countryId = $import->country_id;
            $orgId = $import->organization_id;
            $tdNumber = $import->td_number;
            $folder = storage_path('app/caps-downloads/' . $tdNumber);

            DB::transaction(function () use ($import, $tdNumber, $folder, $header, $items, $data, $user, $countryId, $orgId) {
                $arrivalDate = $this->parseDate($header['arrival_date'] ?? null);
                $submittedDate = $this->parseDate($header['submitted_time'] ?? null);

                $shipment = Shipment::create([
                    'organization_id' => $orgId,
                    'user_id' => $user->id,
                    'country_id' => $countryId,
                    'status' => Shipment::STATUS_RELEASED,
                    'source_type' => 'legacy',
                    'manifest_number' => $header['manifest_number'] ?? null,
                    'carrier_name' => $header['carrier'] ?? null,
                    'port_of_discharge' => $header['port_of_arrival'] ?? null,
                    'freight_total' => $header['total_freight'] ?? 0,
                    'insurance_total' => $header['total_insurance'] ?? 0,
                    'total_packages' => $header['total_packages'] ?? null,
                    'fob_total' => $header['total_fob'] ?? 0,
                    'actual_arrival_date' => $arrivalDate,
                ]);

                $invoiceNumber = $this->makeUniqueInvoiceNumber('CAPS-' . $tdNumber);
                $invoice = Invoice::create([
                    'organization_id' => $orgId,
                    'country_id' => $countryId,
                    'user_id' => $user->id,
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => $submittedDate ?? now()->toDateString(),
                    'total_amount' => $header['total_fob'] ?? null,
                    'status' => 'completed',
                    'processed' => true,
                    'items' => [],
                    'source_type' => 'legacy',
                    'extraction_meta' => [
                        'created_from' => 'caps_history_import',
                        'caps_td_number' => $tdNumber,
                        'supplier_name' => $header['supplier_name'] ?? null,
                    ],
                ]);

                $line = 1;
                foreach ($items as $item) {
                    $desc = $item['description'] ?? null;
                    if (!$desc) continue;
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'organization_id' => $orgId,
                        'user_id' => $user->id,
                        'country_id' => $countryId,
                        'line_number' => $line++,
                        'description' => $desc,
                        'quantity' => $item['packages_count'] ?? null,
                        'line_total' => $item['fob_value'] ?? null,
                        'currency' => 'USD',
                    ]);
                }

                $shipment->invoices()->attach($invoice->id, [
                    'invoice_fob' => $invoice->total_amount,
                ]);

                $screenshotPath = null;
                $declScreenshot = $folder . '/declaration.png';
                if (File::exists($declScreenshot)) {
                    $storageDest = 'imports/declarations/caps-' . $tdNumber . '.png';
                    Storage::put($storageDest, File::get($declScreenshot));
                    $screenshotPath = $storageDest;
                }

                $declarationForm = DeclarationForm::create([
                    'organization_id' => $orgId,
                    'country_id' => $countryId,
                    'shipment_id' => $shipment->id,
                    'invoice_id' => $invoice->id,
                    'form_number' => $tdNumber,
                    'declaration_date' => $submittedDate ?? now()->toDateString(),
                    'total_duty' => ($header['import_duty'] ?? 0) + ($header['wharfage'] ?? 0),
                    'fob_value' => $header['total_fob'] ?? 0,
                    'freight_total' => $header['total_freight'] ?? 0,
                    'insurance_total' => $header['total_insurance'] ?? 0,
                    'cif_value' => ($header['total_fob'] ?? 0) + ($header['total_freight'] ?? 0) + ($header['total_insurance'] ?? 0),
                    'customs_duty_total' => $header['import_duty'] ?? 0,
                    'wharfage_total' => $header['wharfage'] ?? 0,
                    'manifest_number' => $header['manifest_number'] ?? null,
                    'carrier_name' => $header['carrier'] ?? null,
                    'port_of_arrival' => $header['port_of_arrival'] ?? null,
                    'arrival_date' => $arrivalDate,
                    'total_packages' => $header['total_packages'] ?? null,
                    'items' => $items,
                    'source_type' => 'legacy',
                    'source_file_path' => $screenshotPath,
                    'extraction_meta' => [
                        'source' => 'caps_scrape',
                        'caps_td_number' => $tdNumber,
                        'declarant_id' => $header['declarant_id'] ?? null,
                        'declarant_name' => $header['declarant_name'] ?? null,
                        'supplier_name' => $header['supplier_name'] ?? null,
                        'supplier_country' => $header['supplier_country'] ?? null,
                        'importer_id' => $header['importer_id'] ?? null,
                        'importer_name' => $header['importer_name'] ?? null,
                    ],
                ]);

                $line = 1;
                foreach ($items as $item) {
                    $desc = $item['description'] ?? null;
                    if (!$desc) continue;
                    DeclarationFormItem::create([
                        'declaration_form_id' => $declarationForm->id,
                        'invoice_id' => $invoice->id,
                        'organization_id' => $orgId,
                        'user_id' => $user->id,
                        'country_id' => $countryId,
                        'line_number' => $line++,
                        'description' => $desc,
                        'quantity' => $item['packages_count'] ?? null,
                        'line_total' => $item['fob_value'] ?? null,
                        'currency' => 'USD',
                        'hs_code' => $item['tariff_number'] ?? null,
                        'hs_description' => $desc,
                        'meta' => [
                            'cpc' => $item['cpc'] ?? null,
                            'country_of_origin' => $item['country_of_origin'] ?? null,
                            'cif_value' => $item['cif_value'] ?? null,
                            'taxes' => $item['taxes'] ?? [],
                            'total_due' => $item['total_due'] ?? null,
                        ],
                    ]);
                }

                $attachDir = $folder . '/attachments';
                if (File::isDirectory($attachDir)) {
                    foreach (File::files($attachDir) as $attFile) {
                        $storageDest = 'imports/caps-attachments/' . $tdNumber . '/' . $attFile->getFilename();
                        Storage::put($storageDest, File::get($attFile->getPathname()));
                    }
                }

                $shipment->update([
                    'cif_total' => ($shipment->fob_total ?? 0) + ($shipment->freight_total ?? 0) + ($shipment->insurance_total ?? 0),
                ]);

                $import->update([
                    'status' => CapsImport::STATUS_IMPORTED,
                    'shipment_id' => $shipment->id,
                    'declaration_form_id' => $declarationForm->id,
                    'imported_at' => now(),
                ]);
            });

            return true;

        } catch (\Throwable $e) {
            Log::error("CAPS import failed for TD {$import->td_number}: {$e->getMessage()}");
            $import->markAs(CapsImport::STATUS_FAILED, $e->getMessage());

            $this->analyzeAndStoreDiagnosis($import, $e->getMessage(), 'import');

            return false;
        }
    }

    /**
     * Process invoice PDF attachments for a single TD.
     * Extracts line items from invoice PDFs and matches them to declaration items.
     */
    public function processInvoicePDFs(CapsImport $import): array
    {
        if (!$import->declaration_form_id) {
            return ['success' => false, 'error' => 'TD not yet imported'];
        }

        $import->markAs(CapsImport::STATUS_PROCESSING_INVOICES);

        $tdDir = storage_path('app/caps-downloads/' . $import->td_number);
        $attachDir = $tdDir . '/attachments';

        if (!File::isDirectory($attachDir)) {
            $import->markAs(CapsImport::STATUS_COMPLETED);
            return ['success' => true, 'extracted' => 0, 'matched' => 0, 'message' => 'No attachments to process'];
        }

        $pdfFiles = array_filter(File::files($attachDir), function ($file) {
            return strtolower($file->getExtension()) === 'pdf';
        });

        if (empty($pdfFiles)) {
            $import->markAs(CapsImport::STATUS_COMPLETED);
            return ['success' => true, 'extracted' => 0, 'matched' => 0, 'message' => 'No PDF attachments found'];
        }

        $user = $import->user;
        $countryId = $import->country_id;
        $orgId = $import->organization_id;
        $declarationForm = DeclarationForm::withoutGlobalScopes()->find($import->declaration_form_id);

        if (!$declarationForm) {
            $import->markAs(CapsImport::STATUS_FAILED, 'Declaration form not found');
            return ['success' => false, 'error' => 'Declaration form not found'];
        }

        $totalExtracted = 0;
        $totalMatched = 0;
        $errors = [];

        try {
            foreach ($pdfFiles as $pdfFile) {
                $filename = $pdfFile->getFilename();
                $lower = strtolower($filename);

                // Skip B/L files - they don't have line items to match
                if (str_contains($lower, 'bol') || str_contains($lower, 'bolrel')) {
                    continue;
                }

                try {
                    $uploaded = new UploadedFile(
                        $pdfFile->getPathname(),
                        $filename,
                        'application/pdf',
                        null,
                        true
                    );

                    $extracted = $this->invoiceExtractor->extract($uploaded);

                    if (!empty($extracted['extraction_meta']['error'])) {
                        $errors[] = "{$filename}: {$extracted['extraction_meta']['error']}";
                        continue;
                    }

                    $extractedItems = $extracted['items'] ?? [];
                    if (empty($extractedItems)) {
                        $errors[] = "{$filename}: No items extracted";
                        continue;
                    }

                    // Find or create invoice for this PDF
                    $invoiceNumber = $this->makeUniqueInvoiceNumber(
                        $extracted['invoice_number'] ?: ('CAPS-INV-' . $import->td_number . '-' . pathinfo($filename, PATHINFO_FILENAME))
                    );

                    $invoice = Invoice::create([
                        'organization_id' => $orgId,
                        'country_id' => $countryId,
                        'user_id' => $user->id,
                        'invoice_number' => $invoiceNumber,
                        'invoice_date' => $extracted['invoice_date'] ?? now()->toDateString(),
                        'total_amount' => $extracted['total_amount'] ?? null,
                        'status' => 'completed',
                        'processed' => true,
                        'items' => $extractedItems,
                        'source_type' => 'legacy',
                        'source_file_path' => 'imports/caps-attachments/' . $import->td_number . '/' . $filename,
                        'extracted_text' => $extracted['extracted_text'] ?? null,
                        'extraction_meta' => array_merge($extracted['extraction_meta'] ?? [], [
                            'caps_td_number' => $import->td_number,
                            'caps_attachment' => $filename,
                        ]),
                    ]);

                    $line = 1;
                    foreach ($extractedItems as $item) {
                        $desc = $item['description'] ?? null;
                        if (!$desc) continue;

                        InvoiceItem::create([
                            'invoice_id' => $invoice->id,
                            'organization_id' => $orgId,
                            'user_id' => $user->id,
                            'country_id' => $countryId,
                            'line_number' => $item['line_number'] ?? $line++,
                            'sku' => $item['sku'] ?? null,
                            'item_number' => $item['item_number'] ?? null,
                            'description' => $desc,
                            'quantity' => $item['quantity'] ?? null,
                            'unit_price' => $item['unit_price'] ?? null,
                            'line_total' => $item['line_total'] ?? null,
                            'currency' => $item['currency'] ?? $extracted['currency'] ?? 'USD',
                        ]);
                        $totalExtracted++;
                    }

                    // Link invoice to the shipment
                    if ($import->shipment_id) {
                        $shipment = Shipment::withoutGlobalScopes()->find($import->shipment_id);
                        if ($shipment) {
                            $shipment->invoices()->syncWithoutDetaching([
                                $invoice->id => ['invoice_fob' => $invoice->total_amount],
                            ]);
                        }
                    }

                    // Match invoice items to declaration items
                    $matchResult = $this->matcher->matchInvoiceToDeclaration($invoice, $declarationForm, $user);
                    $totalMatched += $matchResult['matched'] ?? 0;

                } catch (\Throwable $e) {
                    Log::error("CAPS invoice processing failed for {$filename} in TD {$import->td_number}: {$e->getMessage()}");
                    $errors[] = "{$filename}: {$e->getMessage()}";
                }
            }

            $import->update([
                'status' => CapsImport::STATUS_COMPLETED,
                'invoices_processed_at' => now(),
            ]);

            if (!empty($errors)) {
                $recovery = $this->errorRecovery->analyzeMultiple($errors, 'invoice_processing', [
                    'td_number' => $import->td_number,
                    'retry_count' => $import->retry_count,
                ]);
                $import->storeAiDiagnosis($recovery);
            }

            return [
                'success' => true,
                'extracted' => $totalExtracted,
                'matched' => $totalMatched,
                'errors' => $errors,
            ];

        } catch (\Throwable $e) {
            $import->markAs(CapsImport::STATUS_FAILED, 'Invoice processing error: ' . $e->getMessage());

            $allErrors = array_merge($errors, [$e->getMessage()]);
            $this->analyzeAndStoreDiagnosis($import, implode("\n", $allErrors), 'invoice_processing');

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Scan existing downloaded folders on disk.
     */
    protected function scanDownloadedFolders(): array
    {
        $downloadsDir = storage_path('app/caps-downloads');
        if (!File::isDirectory($downloadsDir)) {
            return [];
        }

        $folders = glob($downloadsDir . '/0*', GLOB_ONLYDIR);
        return array_map(fn($f) => basename($f), $folders ?: []);
    }

    /**
     * Fetch TD numbers by running the Playwright script.
     */
    protected function fetchTDNumbersViaPlaywright(int $timeout, ?array $creds = null): array
    {
        $creds = $creds ?? [
            'username' => config('services.caps.username', ''),
            'password' => config('services.caps.password', ''),
            'url' => config('services.caps.url', 'https://caps.gov.vg'),
        ];

        $process = new Process(
            $this->buildPlaywrightArgs($creds, ['--all']),
            base_path(), null, null, $timeout + 30
        );

        return $this->scanDownloadedFolders();
    }

    protected function parseDate(?string $value): ?string
    {
        if (!$value) return null;
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    /**
     * Resolve CAPS credentials: per-org first, then .env fallback.
     */
    protected function resolveCapsCredentials(?int $orgId, ?int $countryId = null): array
    {
        if ($orgId) {
            $credential = OrganizationSubmissionCredential::where('organization_id', $orgId)
                ->forCaps()
                ->when($countryId, fn($q) => $q->forCountry($countryId))
                ->active()
                ->first();

            if ($credential && $credential->hasCompleteCapsCredentials()) {
                return $credential->getCapsCredentials();
            }
        }

        return [
            'username' => config('services.caps.username', ''),
            'password' => config('services.caps.password', ''),
            'url' => config('services.caps.url', 'https://caps.gov.vg'),
        ];
    }

    /**
     * Build the Playwright command with org-level credentials.
     */
    protected function buildPlaywrightArgs(array $creds, array $extra = []): array
    {
        $nodePath = base_path('playwright/caps-td-downloader.mjs');
        $args = ['node', $nodePath];

        if (!empty($creds['username'])) {
            $args[] = '--username=' . $creds['username'];
        }
        if (!empty($creds['password'])) {
            $args[] = '--password=' . $creds['password'];
        }
        if (!empty($creds['url'])) {
            $args[] = '--url=' . $creds['url'];
        }

        return array_merge($args, $extra);
    }

    protected function analyzeAndStoreDiagnosis(CapsImport $import, string $errorMessage, string $phase, array $extraContext = []): void
    {
        try {
            $context = array_merge([
                'td_number' => $import->td_number,
                'retry_count' => $import->retry_count,
            ], $extraContext);

            $recovery = $this->errorRecovery->analyze($errorMessage, $phase, $context);
            $import->storeAiDiagnosis($recovery);

            Log::info("CAPS import AI diagnosis for TD {$import->td_number}", [
                'phase' => $phase,
                'category' => $recovery['error_category'] ?? 'unknown',
                'can_retry' => $recovery['can_retry'],
                'diagnosis' => $recovery['diagnosis'],
            ]);
        } catch (\Throwable $e) {
            Log::warning("CAPS import error recovery failed for TD {$import->td_number}: {$e->getMessage()}");
        }
    }

    protected function makeUniqueInvoiceNumber(string $base): string
    {
        $candidate = $base;
        $i = 1;
        while (Invoice::withoutGlobalScopes()->where('invoice_number', $candidate)->exists()) {
            $i++;
            $candidate = $base . '-' . $i;
        }
        return $candidate;
    }
}
