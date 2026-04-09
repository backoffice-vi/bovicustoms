<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\DeclarationForm;
use App\Models\DeclarationFormItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportCapsHistory extends Command
{
    protected $signature = 'caps:import-history
        {--td= : Import a single TD by number}
        {--all : Import all downloaded TDs}
        {--user= : User email to own the imports (default: CAPS_USERNAME from .env)}
        {--dry-run : Show what would be imported without writing to DB}';

    protected $description = 'Import downloaded CAPS Trade Declarations into legacy clearances for classification history';

    protected ?User $importUser = null;
    protected ?Country $bviCountry = null;

    public function handle(): int
    {
        $downloadsDir = storage_path('app/caps-downloads');
        if (!File::isDirectory($downloadsDir)) {
            $this->error("No downloads directory found at: {$downloadsDir}");
            $this->info('Run the downloader first: node playwright/caps-td-downloader.mjs --all');
            return 1;
        }

        $this->bviCountry = Country::where('name', 'like', '%British Virgin%')
            ->orWhere('code', 'VG')
            ->first();
        if (!$this->bviCountry) {
            $this->error('BVI country not found in database.');
            return 1;
        }

        $userEmail = $this->option('user') ?: config('services.caps.username', env('CAPS_USERNAME'));
        $this->importUser = User::where('email', $userEmail)->first();
        if (!$this->importUser) {
            $this->importUser = User::whereNotNull('organization_id')->first();
        }
        if (!$this->importUser) {
            $this->error('No suitable user found for import. Provide --user=email');
            return 1;
        }

        $this->info("Importing as user: {$this->importUser->email} (org: {$this->importUser->organization_id})");
        $this->info("Country: {$this->bviCountry->name} (ID: {$this->bviCountry->id})");

        $singleTd = $this->option('td');
        $runAll = $this->option('all');
        $dryRun = $this->option('dry-run');

        if (!$singleTd && !$runAll) {
            $this->error('Specify --td=NUMBER or --all');
            return 1;
        }

        $folders = $singleTd
            ? [$downloadsDir . '/' . $singleTd]
            : glob($downloadsDir . '/0*', GLOB_ONLYDIR);

        if (empty($folders)) {
            $this->warn('No TD folders found.');
            return 0;
        }

        $this->info("Found " . count($folders) . " TD folder(s) to process.\n");

        $imported = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($folders as $folder) {
            $tdNumber = basename($folder);
            $dataFile = $folder . '/data.json';

            if (!File::exists($dataFile)) {
                $this->warn("  [{$tdNumber}] No data.json found – skipping");
                $skipped++;
                continue;
            }

            // Check if already imported
            $existing = DeclarationForm::withoutGlobalScopes()
                ->where('form_number', $tdNumber)
                ->where('source_type', 'legacy')
                ->first();

            if ($existing) {
                $this->warn("  [{$tdNumber}] Already imported (DeclarationForm #{$existing->id}) – skipping");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $data = json_decode(File::get($dataFile), true);
                $itemCount = count($data['items'] ?? []);
                $attCount = count($data['attachments'] ?? []);
                $this->info("  [{$tdNumber}] Would import: {$itemCount} items, {$attCount} attachments");
                $skipped++;
                continue;
            }

            try {
                $this->importTD($tdNumber, $folder);
                $imported++;
                $this->info("  [{$tdNumber}] Imported successfully");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  [{$tdNumber}] Failed: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Done: {$imported} imported, {$skipped} skipped, {$failed} failed");

        return $failed > 0 ? 1 : 0;
    }

    protected function importTD(string $tdNumber, string $folder): void
    {
        $data = json_decode(File::get($folder . '/data.json'), true);
        $header = $data['header'] ?? [];
        $items = $data['items'] ?? [];

        $user = $this->importUser;
        $countryId = $this->bviCountry->id;
        $orgId = $user->organization_id;

        DB::transaction(function () use ($tdNumber, $folder, $header, $items, $data, $user, $countryId, $orgId) {

            // 1. Create Shipment
            $arrivalDate = $this->parseDate($header['arrival_date'] ?? null);
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

            // 2. Create a placeholder Invoice from the TD header
            $invoiceNumber = $this->makeUniqueInvoiceNumber('CAPS-' . $tdNumber);
            $submittedDate = $this->parseDate($header['submitted_time'] ?? null);

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
                'source_file_path' => null,
                'extracted_text' => null,
                'extraction_meta' => [
                    'created_from' => 'caps_history_import',
                    'caps_td_number' => $tdNumber,
                    'supplier_name' => $header['supplier_name'] ?? null,
                ],
            ]);

            // Create InvoiceItems from TD items
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
                    'unit_price' => null,
                    'line_total' => $item['fob_value'] ?? null,
                    'currency' => 'USD',
                ]);
            }

            // Link invoice to shipment
            $shipment->invoices()->attach($invoice->id, [
                'invoice_fob' => $invoice->total_amount,
            ]);

            // 3. Store declaration screenshot if exists
            $screenshotPath = null;
            $declScreenshot = $folder . '/declaration.png';
            if (File::exists($declScreenshot)) {
                $storageDest = 'imports/declarations/caps-' . $tdNumber . '.png';
                Storage::put($storageDest, File::get($declScreenshot));
                $screenshotPath = $storageDest;
            }

            // 4. Create DeclarationForm
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
                'extracted_text' => null,
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

            // 5. Create DeclarationFormItems (these are the precedents used by ItemClassifier)
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
                    'unit_price' => null,
                    'line_total' => $item['fob_value'] ?? null,
                    'currency' => 'USD',
                    'hs_code' => $item['tariff_number'] ?? null,
                    'hs_description' => $desc,
                    'meta' => [
                        'cpc' => $item['cpc'] ?? null,
                        'country_of_origin' => $item['country_of_origin'] ?? null,
                        'cif_value' => $item['cif_value'] ?? null,
                        'freight' => $item['freight'] ?? null,
                        'insurance' => $item['insurance'] ?? null,
                        'taxes' => $item['taxes'] ?? [],
                        'total_due' => $item['total_due'] ?? null,
                    ],
                ]);
            }

            // 6. Store attachment files
            $attachDir = $folder . '/attachments';
            if (File::isDirectory($attachDir)) {
                foreach (File::files($attachDir) as $attFile) {
                    $storageDest = 'imports/caps-attachments/' . $tdNumber . '/' . $attFile->getFilename();
                    Storage::put($storageDest, File::get($attFile->getPathname()));
                }
            }

            // 7. Update shipment CIF total
            $shipment->update([
                'cif_total' => ($shipment->fob_total ?? 0) + ($shipment->freight_total ?? 0) + ($shipment->insurance_total ?? 0),
            ]);
        });
    }

    protected function parseDate(?string $value): ?string
    {
        if (!$value) return null;
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Handle DD/MM/YYYY format common in CAPS
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        $ts = strtotime($value);
        return $ts !== false ? date('Y-m-d', $ts) : null;
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
