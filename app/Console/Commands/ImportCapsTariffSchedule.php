<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportCapsTariffSchedule extends Command
{
    protected $signature = 'caps:import-tariff-schedule
        {--country-id=1 : Country to import for}
        {--source=storage/app/HMC_100V.3_2024_alpha.xlsx : Path to HMC Excel file}
        {--sheet=TARIFF_CODES : Sheet name to read}
        {--source-tag=HMC_100V.3_2024_alpha : Value to write into customs_codes.source}
        {--apply : Actually write changes (otherwise dry-run)}';

    protected $description = 'Import the official BVI HMC tariff schedule Excel into customs_codes (idempotent, dry-run by default)';

    /**
     * Map dual-duty rows from Excel to a single canonical row.
     *
     * Each item: ['ad_valorem' => ?float, 'specific' => ?float, 'unit' => string]
     */
    private array $excel = [];

    public function handle(): int
    {
        $countryId = (int) $this->option('country-id');
        $sourcePath = $this->option('source');
        $sheetName = $this->option('sheet');
        $sourceTag = $this->option('source-tag');
        $apply = (bool) $this->option('apply');

        if (!is_file($sourcePath)) {
            $this->error("Source file not found: {$sourcePath}");
            return self::FAILURE;
        }

        $this->info("Reading {$sourcePath} (sheet: {$sheetName})...");
        $this->loadExcel($sourcePath, $sheetName);
        $this->info("  Excel codes parsed: " . count($this->excel));

        $local = $this->loadLocal($countryId);
        $this->info("  Local 7-digit codes (country_id={$countryId}): " . count($local));

        $chapters = DB::table('tariff_chapters')
            ->where('country_id', $countryId)
            ->pluck('id', 'chapter_number')
            ->all();

        $plan = $this->buildPlan($local, $chapters);

        $this->printDryRunSummary($plan);

        if (!$apply) {
            $this->warn(PHP_EOL . 'DRY-RUN ONLY. Re-run with --apply to write changes.');
            return self::SUCCESS;
        }

        $this->info(PHP_EOL . 'Applying changes in a single transaction...');
        DB::transaction(function () use ($plan, $countryId, $sourceTag) {
            $this->applyPlan($plan, $countryId, $sourceTag);
        });
        $this->info('Done.');

        return self::SUCCESS;
    }

    private function loadExcel(string $path, string $sheetName): void
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $ss = $reader->load($path);
        $sh = $ss->getSheetByName($sheetName);
        if (!$sh) {
            throw new \RuntimeException("Sheet '{$sheetName}' not found");
        }

        foreach ($sh->getRowIterator(2) as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $c) {
                $cells[] = (string) $c->getValue();
            }
            $code = trim($cells[0] ?? '');
            if (!preg_match('/^\d{7}$/', $code)) {
                continue;
            }
            $duty = isset($cells[1]) && $cells[1] !== '' ? (float) $cells[1] : null;
            $source = trim($cells[2] ?? '');
            $unit = trim($cells[3] ?? '') ?: 'LB';

            if (!isset($this->excel[$code])) {
                $this->excel[$code] = ['ad_valorem' => null, 'specific' => null, 'unit' => $unit];
            }

            // SOURCE_FOR_DUTY = 1 means specific (per-LB dollar). Other values
            // (empty / 2) are ad-valorem percent. Excise goods (alcohol,
            // tobacco, fuel) appear twice: once as specific, once as
            // ad-valorem. Both apply.
            if ($source === '1') {
                $this->excel[$code]['specific'] = $duty;
            } else {
                $this->excel[$code]['ad_valorem'] = $duty;
            }
        }
    }

    /**
     * @return array<string, object> map of 7-digit-no-dots => DB row
     */
    private function loadLocal(int $countryId): array
    {
        $out = [];
        $rows = DB::table('customs_codes')
            ->where('country_id', $countryId)
            ->get();
        foreach ($rows as $r) {
            $digits = preg_replace('/\D/', '', $r->code);
            if (strlen($digits) === 7) {
                $out[$digits] = $r;
            }
        }
        return $out;
    }

    private function buildPlan(array $local, array $chapters): array
    {
        $toInsert = [];
        $toUpdate = [];
        $toDeactivate = [];
        $unchanged = 0;

        foreach ($this->excel as $digits => $rates) {
            $dotted = substr($digits, 0, 4) . '.' . substr($digits, 4);
            $chapterNumber = substr($digits, 0, 2);
            $chapterId = $chapters[$chapterNumber] ?? null;

            $adValorem = $rates['ad_valorem'];
            $specific = $rates['specific'];
            $unit = $rates['unit'];

            $dutyType = 'ad_valorem';
            if ($specific !== null && $adValorem !== null) {
                $dutyType = 'mixed';
            } elseif ($specific !== null) {
                $dutyType = 'specific';
            }

            $payload = [
                'duty_rate' => $adValorem !== null ? round($adValorem, 4) : 0,
                'duty_type' => $dutyType,
                'specific_duty_amount' => $specific !== null ? round($specific, 4) : null,
                'specific_duty_unit' => $specific !== null ? $unit : null,
                'unit_of_measurement' => $unit,
                'tariff_chapter_id' => $chapterId,
            ];

            if (!isset($local[$digits])) {
                $toInsert[$digits] = ['code' => $dotted, 'digits' => $digits, 'payload' => $payload];
                continue;
            }

            $row = $local[$digits];

            // Safety: HMC v3 alpha may not list a SOURCE=1 row even where one
            // exists historically (e.g., excise duties on alcohol). If Excel
            // didn't supply a specific component for this code, preserve the
            // existing specific_duty_amount/unit and keep duty_type=mixed
            // when both are present locally.
            $existSpecific = $row->specific_duty_amount !== null && $row->specific_duty_amount !== ''
                ? (float) $row->specific_duty_amount : null;
            $existSpecificUnit = $row->specific_duty_unit ?: null;

            if ($specific === null && $existSpecific !== null) {
                $payload['specific_duty_amount'] = $existSpecific;
                $payload['specific_duty_unit'] = $existSpecificUnit ?: $payload['unit_of_measurement'];
                $payload['duty_type'] = $adValorem !== null ? 'mixed' : 'specific';
            }

            $diffs = [];
            if ($payload['duty_type'] !== ($row->duty_type ?? null)) {
                $diffs['duty_type'] = [$row->duty_type ?? null, $payload['duty_type']];
            }
            if (abs((float) ($row->duty_rate ?? 0) - (float) $payload['duty_rate']) > 0.0001) {
                $diffs['duty_rate'] = [(float) ($row->duty_rate ?? 0), (float) $payload['duty_rate']];
            }
            $newSpecific = $payload['specific_duty_amount'] !== null ? (float) $payload['specific_duty_amount'] : null;
            if ($existSpecific !== $newSpecific) {
                $diffs['specific_duty_amount'] = [$existSpecific, $newSpecific];
            }

            if (!empty($diffs)) {
                $toUpdate[$digits] = ['code' => $row->code, 'diffs' => $diffs, 'payload' => $payload];
            } else {
                $unchanged++;
            }
        }

        // Local rows not present in Excel.
        foreach ($local as $digits => $row) {
            if (!isset($this->excel[$digits]) && (int) ($row->is_active ?? 1) === 1) {
                $toDeactivate[$digits] = ['code' => $row->code, 'description' => $row->description];
            }
        }

        return [
            'insert' => $toInsert,
            'update' => $toUpdate,
            'deactivate' => $toDeactivate,
            'unchanged' => $unchanged,
        ];
    }

    private function printDryRunSummary(array $plan): void
    {
        $this->newLine();
        $this->info('=== Plan Summary ===');
        $this->info('  To insert:     ' . count($plan['insert']));
        $this->info('  To update:     ' . count($plan['update']));
        $this->info('  To deactivate: ' . count($plan['deactivate']));
        $this->info('  Unchanged:     ' . $plan['unchanged']);

        $byChapter = [];
        foreach ($plan['insert'] as $row) {
            $ch = substr($row['digits'], 0, 2);
            $byChapter[$ch] = ($byChapter[$ch] ?? 0) + 1;
        }
        ksort($byChapter);

        if (!empty($byChapter)) {
            $this->newLine();
            $this->info('Inserts by chapter (top 15):');
            arsort($byChapter);
            $shown = 0;
            foreach ($byChapter as $ch => $n) {
                if ($shown++ >= 15) break;
                $this->line(sprintf('    Ch %s: %d', $ch, $n));
            }
        }

        if (!empty($plan['update'])) {
            $this->newLine();
            $this->info('Sample rate changes (first 10):');
            $shown = 0;
            foreach ($plan['update'] as $u) {
                if ($shown++ >= 10) break;
                $diffs = [];
                foreach ($u['diffs'] as $f => $vals) {
                    $diffs[] = sprintf('%s %s -> %s', $f, var_export($vals[0], true), var_export($vals[1], true));
                }
                $this->line(sprintf('    %s  | %s', $u['code'], implode(' | ', $diffs)));
            }
        }

        if (!empty($plan['deactivate'])) {
            $this->newLine();
            $this->info('To deactivate (first 10):');
            $shown = 0;
            foreach ($plan['deactivate'] as $d) {
                if ($shown++ >= 10) break;
                $this->line(sprintf('    %s  %s', $d['code'], substr((string) $d['description'], 0, 60)));
            }
        }
    }

    private function applyPlan(array $plan, int $countryId, string $sourceTag): void
    {
        $now = now();
        $stamp = $now->toDateTimeString();
        $deactNote = "Not present in HMC v3 alpha 2024; deactivated {$now->toDateString()}";

        // INSERTS
        $batch = [];
        foreach ($plan['insert'] as $digits => $row) {
            $batch[] = [
                'country_id' => $countryId,
                'code' => $row['code'],
                'description' => '',
                'duty_rate' => $row['payload']['duty_rate'],
                'duty_type' => $row['payload']['duty_type'],
                'specific_duty_amount' => $row['payload']['specific_duty_amount'],
                'specific_duty_unit' => $row['payload']['specific_duty_unit'],
                'unit_of_measurement' => $row['payload']['unit_of_measurement'],
                'tariff_chapter_id' => $row['payload']['tariff_chapter_id'],
                'hs_code_version' => '2024',
                'code_level' => 'subheading',
                'source' => $sourceTag,
                'is_active' => 1,
                'classification_keywords' => json_encode([]),
                'applicable_note_ids' => json_encode([]),
                'notes' => 'Imported from HMC v3 alpha 2024; description pending',
                'created_at' => $stamp,
                'updated_at' => $stamp,
            ];
            if (count($batch) >= 500) {
                DB::table('customs_codes')->insert($batch);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            DB::table('customs_codes')->insert($batch);
        }
        $this->info('  Inserted: ' . count($plan['insert']));

        // UPDATES
        $i = 0;
        foreach ($plan['update'] as $digits => $u) {
            DB::table('customs_codes')
                ->where('country_id', $countryId)
                ->where('code', $u['code'])
                ->update([
                    'duty_rate' => $u['payload']['duty_rate'],
                    'duty_type' => $u['payload']['duty_type'],
                    'specific_duty_amount' => $u['payload']['specific_duty_amount'],
                    'specific_duty_unit' => $u['payload']['specific_duty_unit'],
                    'unit_of_measurement' => $u['payload']['unit_of_measurement'],
                    'source' => $sourceTag,
                    'updated_at' => $stamp,
                ]);
            $i++;
        }
        $this->info('  Updated:  ' . $i);

        // DEACTIVATIONS
        $i = 0;
        foreach ($plan['deactivate'] as $digits => $d) {
            $existing = DB::table('customs_codes')
                ->where('country_id', $countryId)
                ->where('code', $d['code'])
                ->value('notes');
            $newNotes = trim(($existing ? $existing . ' | ' : '') . $deactNote);
            DB::table('customs_codes')
                ->where('country_id', $countryId)
                ->where('code', $d['code'])
                ->update([
                    'is_active' => 0,
                    'notes' => $newNotes,
                    'updated_at' => $stamp,
                ]);
            $i++;
        }
        $this->info('  Deactivated: ' . $i);
    }
}
