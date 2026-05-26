<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExportReferenceSnapshot extends Command
{
    protected $signature = 'caps:export-reference-snapshot {--country-id=1 : Country to snapshot} {--label=hmc-import-backup : Folder label}';

    protected $description = 'Snapshot customs_codes, country_reference_data, and customs_code_rate_overrides to storage/exports/ for audit / rollback';

    public function handle(): int
    {
        $countryId = (int) $this->option('country-id');
        $label = $this->option('label');
        $timestamp = now()->format('Ymd-His');
        $dir = storage_path("exports/{$label}-{$timestamp}");

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $tables = [
            'customs_codes' => DB::table('customs_codes')->where('country_id', $countryId)->get(),
            'country_reference_data' => DB::table('country_reference_data')->where('country_id', $countryId)->get(),
            'customs_code_rate_overrides' => DB::table('customs_code_rate_overrides')->where('country_id', $countryId)->get(),
        ];

        foreach ($tables as $name => $rows) {
            $path = $dir . DIRECTORY_SEPARATOR . $name . '.json';
            File::put($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("  {$name}: " . count($rows) . " rows -> {$path}");
        }

        $this->info(PHP_EOL . "Snapshot complete: {$dir}");

        return self::SUCCESS;
    }
}
