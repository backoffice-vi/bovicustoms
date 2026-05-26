<?php

namespace App\Console\Commands;

use App\Models\CountryReferenceData;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CleanupStaleReferenceData extends Command
{
    protected $signature = 'caps:cleanup-stale-reference-data
        {--country-id=1 : Country to clean}
        {--source=storage/app/HMC_100V.3_2024_alpha.xlsx : Path to HMC Excel file}
        {--apply : Actually deactivate stale rows (otherwise dry-run)}';

    protected $description = 'Deactivate country_reference_data rows whose codes are not in the HMC v3 alpha 2024 Excel (CPC, ADDITIONAL INFORMATION, OFFICE CODES sheets).';

    private const SHEET_TO_TYPE = [
        'CPC_CODES' => 'cpc',
        'ADDITIONAL INFORMATION' => 'additional_info',
        'OFFICE CODES' => 'port',
    ];

    public function handle(): int
    {
        $countryId = (int) $this->option('country-id');
        $sourcePath = $this->option('source');
        $apply = (bool) $this->option('apply');

        if (!is_file($sourcePath)) {
            $this->error("Source file not found: {$sourcePath}");
            return self::FAILURE;
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $ss = $reader->load($sourcePath);

        $now = now();
        $deactNote = "Not in HMC v3 alpha 2024; deactivated {$now->toDateString()}";

        foreach (self::SHEET_TO_TYPE as $sheetName => $refType) {
            $sh = $ss->getSheetByName($sheetName);
            if (!$sh) {
                $this->warn("Sheet '{$sheetName}' not found, skipping");
                continue;
            }

            $excelCodes = [];
            foreach ($sh->getRowIterator(2) as $r) {
                $cells = [];
                foreach ($r->getCellIterator() as $c) {
                    $cells[] = (string) $c->getValue();
                }
                $code = trim($cells[0] ?? '');
                if ($code === '' || strtoupper($code) === 'CODE') {
                    continue;
                }
                $excelCodes[$code] = true;
            }

            $stale = CountryReferenceData::where('country_id', $countryId)
                ->where('reference_type', $refType)
                ->where('is_active', true)
                ->whereNotIn('code', array_keys($excelCodes))
                ->get();

            $this->info(sprintf('[%s -> %s]  Excel: %d codes  Local active not in Excel: %d',
                $sheetName, $refType, count($excelCodes), $stale->count()));

            foreach ($stale->take(8) as $r) {
                $this->line(sprintf('    %-6s  %s', $r->code, substr((string) $r->label, 0, 60)));
            }
            if ($stale->count() > 8) {
                $this->line('    ... and ' . ($stale->count() - 8) . ' more');
            }

            if ($apply) {
                foreach ($stale as $r) {
                    $r->is_active = false;
                    $existingNote = (string) ($r->notes ?? '');
                    if (!str_contains($existingNote, 'HMC v3 alpha')) {
                        $r->notes = trim(($existingNote ? $existingNote . ' | ' : '') . $deactNote);
                    }
                    $r->save();
                }
                $this->info("    -> deactivated {$stale->count()} rows");
            }
        }

        if (!$apply) {
            $this->newLine();
            $this->warn('DRY-RUN ONLY. Re-run with --apply to deactivate.');
        }

        return self::SUCCESS;
    }
}
