<?php

namespace App\Console\Commands;

use App\Models\CustomsCode;
use App\Models\TariffChapter;
use App\Models\TariffChapterNote;
use App\Models\ClassificationExclusion;
use App\Models\ExemptionCategory;
use App\Models\Country;
use App\Services\QdrantClient;
use App\Services\EmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncTariffVectors extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tariff:sync-vectors 
                            {--country= : Specific country ID to sync}
                            {--type= : Specific type to sync (code, note, exclusion, exemption)}
                            {--recreate : Delete and recreate the collection}
                            {--batch-size=50 : Number of items per batch}
                            {--dry-run : Show what would be synced without actually syncing}';

    /**
     * The console command description.
     */
    protected $description = 'Sync tariff data to Qdrant vector database for semantic search';

    protected QdrantClient $qdrant;
    protected EmbeddingService $embeddings;
    protected int $batchSize;
    protected bool $dryRun;

    public function __construct(QdrantClient $qdrant, EmbeddingService $embeddings)
    {
        parent::__construct();
        $this->qdrant = $qdrant;
        $this->embeddings = $embeddings;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Allow long execution time and more memory
        set_time_limit(0);
        ini_set('memory_limit', '1G');
        
        $this->batchSize = (int) $this->option('batch-size');
        $this->dryRun = (bool) $this->option('dry-run');
        $countryId = $this->option('country');
        $type = $this->option('type');

        $this->info('🚀 Starting Tariff Vector Sync');
        $this->newLine();

        if ($this->dryRun) {
            $this->warn('DRY RUN MODE - No data will be synced');
            $this->newLine();
        }

        // Test connections
        if (!$this->testConnections()) {
            return 1;
        }

        // Handle collection recreation
        if ($this->option('recreate')) {
            $this->recreateCollection();
        } else {
            // Ensure collection exists
            $this->ensureCollectionExists();
        }

        $this->newLine();

        // Track totals
        $totalSynced = 0;

        // Sync based on type filter or all
        if (!$type || $type === 'code') {
            $totalSynced += $this->syncCustomsCodes($countryId);
        }

        if (!$type || $type === 'note') {
            $totalSynced += $this->syncChapterNotes($countryId);
        }

        if (!$type || $type === 'exclusion') {
            $totalSynced += $this->syncExclusionRules($countryId);
        }

        if (!$type || $type === 'exemption') {
            $totalSynced += $this->syncExemptions($countryId);
        }

        $this->newLine();
        $this->info("✅ Sync complete! Total items synced: {$totalSynced}");

        // Show collection stats
        $this->showCollectionStats();

        return 0;
    }

    /**
     * Test Qdrant and OpenAI connections
     */
    protected function testConnections(): bool
    {
        $this->info('Testing connections...');

        // Test Qdrant
        $qdrantTest = $this->qdrant->testConnection();
        if (!$qdrantTest['success']) {
            $this->error('❌ Qdrant connection failed: ' . $qdrantTest['message']);
            return false;
        }
        $this->info('✓ Qdrant connected');

        // Test OpenAI embeddings
        $embeddingTest = $this->embeddings->test();
        if (!$embeddingTest['success']) {
            $this->error('❌ OpenAI embeddings failed: ' . $embeddingTest['message']);
            return false;
        }
        $this->info("✓ OpenAI embeddings working ({$embeddingTest['model']}, {$embeddingTest['dimensions']} dimensions)");

        return true;
    }

    /**
     * Ensure the collection exists
     */
    protected function ensureCollectionExists(): void
    {
        $collectionName = $this->qdrant->getCollectionName();
        
        if (!$this->qdrant->collectionExists()) {
            $this->info("Creating collection '{$collectionName}'...");
            
            if (!$this->dryRun) {
                $result = $this->qdrant->createCollection(null, $this->embeddings->getDimensions());
                
                if (!$result['success']) {
                    $this->error('Failed to create collection: ' . $result['message']);
                    return;
                }
            }
            
            $this->info("✓ Collection created");
        } else {
            $this->info("✓ Collection '{$collectionName}' exists");
        }
    }

    /**
     * Recreate the collection (delete and create fresh)
     */
    protected function recreateCollection(): void
    {
        $collectionName = $this->qdrant->getCollectionName();
        
        if ($this->qdrant->collectionExists()) {
            $this->warn("Deleting existing collection '{$collectionName}'...");
            
            if (!$this->dryRun) {
                $this->qdrant->deleteCollection();
            }
        }
        
        $this->info("Creating fresh collection '{$collectionName}'...");
        
        if (!$this->dryRun) {
            $result = $this->qdrant->createCollection(null, $this->embeddings->getDimensions());
            
            if (!$result['success']) {
                $this->error('Failed to create collection: ' . $result['message']);
                return;
            }
        }
        
        $this->info("✓ Collection recreated");
    }

    /**
     * Sync customs codes to Qdrant
     */
    protected function syncCustomsCodes(?string $countryId): int
    {
        $this->info('📦 Syncing Customs Codes...');

        $query = CustomsCode::with(['tariffChapter']);
        
        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        $total = $query->count();
        
        if ($total === 0) {
            $this->warn('No customs codes found to sync');
            return 0;
        }

        $this->info("Found {$total} codes to sync");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $synced = 0;
        $errors = 0;

        // Process in chunks
        $query->chunk($this->batchSize, function ($codes) use ($bar, &$synced, &$errors) {
            $points = [];
            $texts = [];
            
            foreach ($codes as $code) {
                $codeData = [
                    'code' => $code->code,
                    'description' => $code->description,
                    'chapter_title' => $code->tariffChapter?->title,
                    'keywords' => $code->classification_keywords ?? [],
                    'inclusion_hints' => $code->inclusion_hints,
                ];
                
                $texts[] = $this->embeddings->buildCodeEmbeddingText($codeData);
                $points[] = [
                    'id_data' => [
                        'type' => 'code',
                        'code' => $code->code,
                        'description' => $code->description,
                        'duty_rate' => $code->duty_rate,
                        'chapter_number' => $code->tariffChapter?->chapter_number,
                        'chapter_title' => $code->tariffChapter?->title,
                        'country_id' => $code->country_id,
                        'customs_code_id' => $code->id,
                        'unit' => $code->unit_of_measurement,
                        'keywords' => $code->classification_keywords ?? [],
                    ],
                ];
            }

            if ($this->dryRun) {
                $synced += count($points);
                $bar->advance(count($points));
                return;
            }

            // Generate embeddings for batch
            $embeddings = $this->embeddings->embedBatch($texts);

            // Build points with vectors
            $qdrantPoints = [];
            foreach ($points as $index => $point) {
                if (isset($embeddings[$index]) && $embeddings[$index]) {
                    $qdrantPoints[] = [
                        'id' => $this->generatePointId('code', $point['id_data']['customs_code_id']),
                        'vector' => $embeddings[$index],
                        'payload' => $point['id_data'],
                    ];
                }
            }

            if (!empty($qdrantPoints)) {
                $result = $this->qdrant->upsertPoints($qdrantPoints);
                
                if ($result['success']) {
                    $synced += count($qdrantPoints);
                } else {
                    $errors += count($qdrantPoints);
                    Log::error('Failed to upsert code points', ['error' => $result['message']]);
                }
            }

            $bar->advance(count($codes));
        });

        $bar->finish();
        $this->newLine();
        $this->info("  ✓ Synced {$synced} codes" . ($errors > 0 ? " ({$errors} errors)" : ""));

        return $synced;
    }

    /**
     * Sync chapter notes to Qdrant
     */
    protected function syncChapterNotes(?string $countryId): int
    {
        $this->info('📝 Syncing Chapter Notes...');

        $query = TariffChapterNote::with(['chapter']);
        
        if ($countryId) {
            $query->whereHas('chapter', function ($q) use ($countryId) {
                $q->where('country_id', $countryId);
            });
        }

        $total = $query->count();
        
        if ($total === 0) {
            $this->warn('No chapter notes found to sync');
            return 0;
        }

        $this->info("Found {$total} notes to sync");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $synced = 0;
        $errors = 0;

        $query->chunk($this->batchSize, function ($notes) use ($bar, &$synced, &$errors) {
            $points = [];
            $texts = [];

            foreach ($notes as $note) {
                $noteData = [
                    'chapter_number' => $note->chapter?->chapter_number,
                    'note_type' => $note->note_type,
                    'note_text' => $note->note_text,
                ];
                
                $texts[] = $this->embeddings->buildNoteEmbeddingText($noteData);
                $points[] = [
                    'id_data' => [
                        'type' => 'note',
                        'note_id' => $note->id,
                        'chapter_number' => $note->chapter?->chapter_number,
                        'chapter_title' => $note->chapter?->title,
                        'note_number' => $note->note_number,
                        'note_type' => $note->note_type,
                        'note_text' => substr($note->note_text, 0, 5000), // Limit text in payload
                        'country_id' => $note->chapter?->country_id,
                    ],
                ];
            }

            if ($this->dryRun) {
                $synced += count($points);
                $bar->advance(count($points));
                return;
            }

            $embeddings = $this->embeddings->embedBatch($texts);

            $qdrantPoints = [];
            foreach ($points as $index => $point) {
                if (isset($embeddings[$index]) && $embeddings[$index]) {
                    $qdrantPoints[] = [
                        'id' => $this->generatePointId('note', $point['id_data']['note_id']),
                        'vector' => $embeddings[$index],
                        'payload' => $point['id_data'],
                    ];
                }
            }

            if (!empty($qdrantPoints)) {
                $result = $this->qdrant->upsertPoints($qdrantPoints);
                
                if ($result['success']) {
                    $synced += count($qdrantPoints);
                } else {
                    $errors += count($qdrantPoints);
                }
            }

            $bar->advance(count($notes));
        });

        $bar->finish();
        $this->newLine();
        $this->info("  ✓ Synced {$synced} notes" . ($errors > 0 ? " ({$errors} errors)" : ""));

        return $synced;
    }

    /**
     * Sync exclusion rules to Qdrant
     */
    protected function syncExclusionRules(?string $countryId): int
    {
        $this->info('🚫 Syncing Exclusion Rules...');

        $query = ClassificationExclusion::with(['sourceChapter', 'targetChapter']);
        
        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        $total = $query->count();
        
        if ($total === 0) {
            $this->warn('No exclusion rules found to sync');
            return 0;
        }

        $this->info("Found {$total} exclusion rules to sync");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $synced = 0;
        $errors = 0;

        $query->chunk($this->batchSize, function ($exclusions) use ($bar, &$synced, &$errors) {
            $points = [];
            $texts = [];

            foreach ($exclusions as $exclusion) {
                $exclusionData = [
                    'description_pattern' => $exclusion->description_pattern,
                    'source_chapter' => $exclusion->sourceChapter?->chapter_number,
                    'target_chapter' => $exclusion->targetChapter?->chapter_number ?? $exclusion->target_heading,
                    'source_note_reference' => $exclusion->source_note_reference,
                ];
                
                $texts[] = $this->embeddings->buildExclusionEmbeddingText($exclusionData);
                $points[] = [
                    'id_data' => [
                        'type' => 'exclusion',
                        'exclusion_id' => $exclusion->id,
                        'source_chapter' => $exclusion->sourceChapter?->chapter_number,
                        'target_chapter' => $exclusion->targetChapter?->chapter_number,
                        'target_heading' => $exclusion->target_heading,
                        'description_pattern' => $exclusion->description_pattern,
                        'source_note_reference' => $exclusion->source_note_reference,
                        'country_id' => $exclusion->country_id,
                    ],
                ];
            }

            if ($this->dryRun) {
                $synced += count($points);
                $bar->advance(count($points));
                return;
            }

            $embeddings = $this->embeddings->embedBatch($texts);

            $qdrantPoints = [];
            foreach ($points as $index => $point) {
                if (isset($embeddings[$index]) && $embeddings[$index]) {
                    $qdrantPoints[] = [
                        'id' => $this->generatePointId('exclusion', $point['id_data']['exclusion_id']),
                        'vector' => $embeddings[$index],
                        'payload' => $point['id_data'],
                    ];
                }
            }

            if (!empty($qdrantPoints)) {
                $result = $this->qdrant->upsertPoints($qdrantPoints);
                
                if ($result['success']) {
                    $synced += count($qdrantPoints);
                } else {
                    $errors += count($qdrantPoints);
                }
            }

            $bar->advance(count($exclusions));
        });

        $bar->finish();
        $this->newLine();
        $this->info("  ✓ Synced {$synced} exclusion rules" . ($errors > 0 ? " ({$errors} errors)" : ""));

        return $synced;
    }

    /**
     * Sync exemptions to Qdrant
     */
    protected function syncExemptions(?string $countryId): int
    {
        $this->info('🎫 Syncing Exemptions...');

        $query = ExemptionCategory::with(['conditions']);
        
        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        $total = $query->count();
        
        if ($total === 0) {
            $this->warn('No exemptions found to sync');
            return 0;
        }

        $this->info("Found {$total} exemptions to sync");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $synced = 0;
        $errors = 0;

        $query->chunk($this->batchSize, function ($exemptions) use ($bar, &$synced, &$errors) {
            $points = [];
            $texts = [];

            foreach ($exemptions as $exemption) {
                $exemptionData = [
                    'name' => $exemption->name,
                    'description' => $exemption->description,
                    'conditions' => $exemption->conditions->pluck('description')->toArray(),
                    'applies_to_codes' => $exemption->applies_to_codes,
                ];
                
                $texts[] = $this->embeddings->buildExemptionEmbeddingText($exemptionData);
                $points[] = [
                    'id_data' => [
                        'type' => 'exemption',
                        'exemption_id' => $exemption->id,
                        'name' => $exemption->name,
                        'description' => $exemption->description,
                        'legal_reference' => $exemption->legal_reference,
                        'country_id' => $exemption->country_id,
                    ],
                ];
            }

            if ($this->dryRun) {
                $synced += count($points);
                $bar->advance(count($points));
                return;
            }

            $embeddings = $this->embeddings->embedBatch($texts);

            $qdrantPoints = [];
            foreach ($points as $index => $point) {
                if (isset($embeddings[$index]) && $embeddings[$index]) {
                    $qdrantPoints[] = [
                        'id' => $this->generatePointId('exemption', $point['id_data']['exemption_id']),
                        'vector' => $embeddings[$index],
                        'payload' => $point['id_data'],
                    ];
                }
            }

            if (!empty($qdrantPoints)) {
                $result = $this->qdrant->upsertPoints($qdrantPoints);
                
                if ($result['success']) {
                    $synced += count($qdrantPoints);
                } else {
                    $errors += count($qdrantPoints);
                }
            }

            $bar->advance(count($exemptions));
        });

        $bar->finish();
        $this->newLine();
        $this->info("  ✓ Synced {$synced} exemptions" . ($errors > 0 ? " ({$errors} errors)" : ""));

        return $synced;
    }

    /**
     * Generate a unique point ID for Qdrant
     */
    protected function generatePointId(string $type, int $id): int
    {
        // Create unique ID by combining type prefix with actual ID
        $typePrefix = match ($type) {
            'code' => 1,
            'note' => 2,
            'exclusion' => 3,
            'exemption' => 4,
            default => 9,
        };
        
        // Combine: type prefix (1 digit) + id (up to 9 digits)
        return (int) ($typePrefix . str_pad($id, 9, '0', STR_PAD_LEFT));
    }

    /**
     * Show collection statistics
     */
    protected function showCollectionStats(): void
    {
        $info = $this->qdrant->getCollectionInfo();
        
        if ($info) {
            $this->newLine();
            $this->info('📊 Collection Statistics:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Points', number_format($info['points_count'] ?? 0)],
                    ['Vectors Size', ($info['config']['params']['vectors']['size'] ?? 'N/A')],
                    ['Status', $info['status'] ?? 'Unknown'],
                ]
            );
        }
    }
}
