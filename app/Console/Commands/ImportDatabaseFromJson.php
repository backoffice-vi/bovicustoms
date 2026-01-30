<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ImportDatabaseFromJson extends Command
{
    protected $signature = 'db:import-json 
                            {--path=storage/exports/sqlite-backup : Path to JSON export files}
                            {--fresh : Run fresh migrations before import}
                            {--table=* : Import only specific tables}';
    
    protected $description = 'Import database from JSON files (for SQLite to MySQL migration)';

    // Define import order to handle foreign key dependencies
    protected array $importOrder = [
        // Core tables (no dependencies)
        'users',
        'countries',
        'organizations',
        'subscription_plans',
        
        // Tables depending on users
        'organization_user',
        'password_reset_tokens',
        'personal_access_tokens',
        'notifications',
        
        // Tables depending on countries
        'tariff_sections',
        'tariff_chapters',
        'tariff_section_notes',
        'tariff_chapter_notes',
        'customs_codes',
        'customs_code_history',
        'country_levies',
        'additional_levies',
        'exemption_categories',
        'exemption_conditions',
        'classification_exclusions',
        'classification_rules',
        'prohibited_goods',
        'restricted_goods',
        'warehousing_restrictions',
        'law_documents',
        'country_support_documents',
        'country_form_templates',
        'country_reference_data',
        
        // Web form tables
        'web_form_targets',
        'web_form_pages',
        'web_form_field_mappings',
        'web_form_dropdown_values',
        'web_form_submissions',
        
        // Trade/business tables
        'trade_contacts',
        'agent_organization_clients',
        'shipments',
        'invoices',
        'invoice_items',
        'shipment_invoices',
        'shipping_documents',
        'declaration_forms',
        'declaration_form_items',
        'filled_declaration_forms',
        'submission_attachments',
        'invoice_declaration_matches',
        
        // Analytics/logs
        'page_visits',
        'waitlist_signups',
        'public_classification_logs',
        
        // System tables
        'failed_jobs',
        'jobs',
    ];

    public function handle()
    {
        $exportPath = base_path($this->option('path'));
        
        if (!File::isDirectory($exportPath)) {
            $this->error("Export directory not found: {$exportPath}");
            return Command::FAILURE;
        }

        // Read manifest
        $manifestPath = "{$exportPath}/_manifest.json";
        if (!File::exists($manifestPath)) {
            $this->error("Manifest file not found. Please ensure you have a valid export.");
            return Command::FAILURE;
        }

        $manifest = json_decode(File::get($manifestPath), true);
        $this->info("Importing from export created at: {$manifest['exported_at']}");
        $this->info("Source database: {$manifest['source']}");
        $this->newLine();

        // Run fresh migrations if requested
        if ($this->option('fresh')) {
            $this->warn("Running fresh migrations...");
            $this->call('migrate:fresh', ['--force' => true]);
            $this->newLine();
        }

        // Get list of tables to import
        $specificTables = $this->option('table');
        $tablesToImport = !empty($specificTables) 
            ? $specificTables 
            : $this->getOrderedTables($manifest['tables']);

        $this->info("Importing " . count($tablesToImport) . " tables...\n");

        // Disable foreign key checks for MySQL
        $connection = config('database.default');
        if ($connection === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        $imported = 0;
        $totalRecords = 0;
        $errors = [];

        foreach ($tablesToImport as $tableName) {
            $jsonFile = "{$exportPath}/{$tableName}.json";
            
            if (!File::exists($jsonFile)) {
                $this->line("  Skipping: {$tableName} (no export file)");
                continue;
            }

            if (!Schema::hasTable($tableName)) {
                $this->warn("  Skipping: {$tableName} (table doesn't exist in database)");
                continue;
            }

            try {
                $data = json_decode(File::get($jsonFile), true);
                
                if (empty($data)) {
                    $this->line("  Skipping: {$tableName} (empty data)");
                    continue;
                }

                // Clear existing data
                DB::table($tableName)->truncate();

                // Insert in chunks
                $chunks = array_chunk($data, 500);
                foreach ($chunks as $chunk) {
                    // Handle JSON fields that need to be re-encoded for MySQL
                    $chunk = array_map(function ($row) {
                        foreach ($row as $key => $value) {
                            // If it's already an array/object from JSON decode, re-encode it
                            if (is_array($value)) {
                                $row[$key] = json_encode($value);
                            }
                        }
                        return $row;
                    }, $chunk);
                    
                    DB::table($tableName)->insert($chunk);
                }

                $count = count($data);
                $this->info("  Imported: {$tableName} ({$count} records)");
                $imported++;
                $totalRecords += $count;

            } catch (\Exception $e) {
                $this->error("  Error importing {$tableName}: " . $e->getMessage());
                $errors[$tableName] = $e->getMessage();
            }
        }

        // Re-enable foreign key checks
        if ($connection === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->newLine();
        $this->info("Import complete!");
        $this->newLine();
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Tables Imported', $imported],
                ['Total Records', number_format($totalRecords)],
                ['Errors', count($errors)],
            ]
        );

        if (!empty($errors)) {
            $this->newLine();
            $this->error("Errors encountered:");
            foreach ($errors as $table => $error) {
                $this->line("  - {$table}: {$error}");
            }
        }

        return empty($errors) ? Command::SUCCESS : Command::FAILURE;
    }

    protected function getOrderedTables(array $manifestTables): array
    {
        $ordered = [];
        
        // First, add tables in our defined order
        foreach ($this->importOrder as $table) {
            if (isset($manifestTables[$table]) && $manifestTables[$table]['exported']) {
                $ordered[] = $table;
            }
        }

        // Then add any remaining tables not in our order
        foreach ($manifestTables as $table => $info) {
            if ($info['exported'] && !in_array($table, $ordered)) {
                $ordered[] = $table;
            }
        }

        return $ordered;
    }
}
