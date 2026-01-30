<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ExportDatabaseToJson extends Command
{
    protected $signature = 'db:export-json {--path=storage/exports/sqlite-backup}';
    protected $description = 'Export all database tables to JSON files for migration';

    public function handle()
    {
        $exportPath = $this->option('path');
        
        // Create export directory
        $fullPath = base_path($exportPath);
        if (!File::isDirectory($fullPath)) {
            File::makeDirectory($fullPath, 0755, true);
        }

        // Get all tables
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        
        $this->info("Exporting " . count($tables) . " tables to {$exportPath}...\n");
        
        $exportInfo = [
            'exported_at' => now()->toIso8601String(),
            'source' => 'sqlite',
            'tables' => [],
        ];

        foreach ($tables as $table) {
            $tableName = $table->name;
            
            // Skip Laravel's migrations table - we'll run fresh migrations
            if ($tableName === 'migrations') {
                $this->line("  Skipping: {$tableName} (will use fresh migrations)");
                continue;
            }

            $count = DB::table($tableName)->count();
            
            if ($count === 0) {
                $this->line("  Skipping: {$tableName} (empty)");
                $exportInfo['tables'][$tableName] = ['count' => 0, 'exported' => false];
                continue;
            }

            // Export in chunks for large tables
            $data = [];
            DB::table($tableName)->orderBy('id')->chunk(1000, function ($rows) use (&$data) {
                foreach ($rows as $row) {
                    $data[] = (array) $row;
                }
            });

            // Handle tables without 'id' column
            if (empty($data)) {
                $data = DB::table($tableName)->get()->map(fn($row) => (array) $row)->toArray();
            }

            $filePath = "{$fullPath}/{$tableName}.json";
            File::put($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $this->info("  Exported: {$tableName} ({$count} records)");
            $exportInfo['tables'][$tableName] = ['count' => $count, 'exported' => true];
        }

        // Save export manifest
        File::put("{$fullPath}/_manifest.json", json_encode($exportInfo, JSON_PRETTY_PRINT));

        $this->newLine();
        $this->info("Export complete! Files saved to: {$exportPath}");
        $this->info("Manifest saved to: {$exportPath}/_manifest.json");
        
        // Summary
        $exported = collect($exportInfo['tables'])->where('exported', true)->count();
        $totalRecords = collect($exportInfo['tables'])->sum('count');
        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Tables Exported', $exported],
                ['Total Records', number_format($totalRecords)],
                ['Export Location', $fullPath],
            ]
        );

        return Command::SUCCESS;
    }
}
