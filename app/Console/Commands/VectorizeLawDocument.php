<?php

namespace App\Console\Commands;

use App\Services\QdrantClient;
use App\Services\EmbeddingService;
use App\Services\DocumentTextExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VectorizeLawDocument extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'law:vectorize 
                            {path : Path to the PDF file}
                            {--chunk-size=1000 : Approximate characters per chunk}
                            {--overlap=200 : Character overlap between chunks}
                            {--country=1 : Country ID for the law document}
                            {--dry-run : Show what would be uploaded without actually uploading}';

    /**
     * The console command description.
     */
    protected $description = 'Vectorize a law document PDF and upload to Qdrant for semantic search';

    protected QdrantClient $qdrant;
    protected EmbeddingService $embeddings;
    protected DocumentTextExtractor $extractor;

    public function __construct(
        QdrantClient $qdrant, 
        EmbeddingService $embeddings,
        DocumentTextExtractor $extractor
    ) {
        parent::__construct();
        $this->qdrant = $qdrant;
        $this->embeddings = $embeddings;
        $this->extractor = $extractor;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Allow long execution time and more memory
        set_time_limit(0);
        ini_set('memory_limit', '2G');
        
        $path = $this->argument('path');
        $chunkSize = (int) $this->option('chunk-size');
        $overlap = (int) $this->option('overlap');
        $countryId = (int) $this->option('country');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('📄 Law Document Vectorization');
        $this->newLine();

        // Validate file exists
        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return 1;
        }

        $this->info("File: {$path}");
        $this->info("Chunk size: {$chunkSize} chars, Overlap: {$overlap} chars");
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be uploaded');
            $this->newLine();
        }

        // Test connections
        if (!$this->testConnections()) {
            return 1;
        }

        // Ensure collection exists
        $this->ensureCollectionExists();

        // Extract text based on file type
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $this->info("📖 Reading {$extension} file...");
        
        if ($extension === 'txt') {
            $text = file_get_contents($path);
        } elseif ($extension === 'pdf') {
            $text = $this->extractPdfLocally($path);
        } else {
            $this->error("Unsupported file type: {$extension}");
            return 1;
        }

        if (empty($text)) {
            $this->error('Could not extract text from PDF');
            return 1;
        }

        $this->info('✓ Extracted ' . number_format(strlen($text)) . ' characters');
        $this->newLine();

        // Chunk the text
        $this->info('✂️ Chunking document...');
        $chunks = $this->chunkText($text, $chunkSize, $overlap);
        $this->info('✓ Created ' . count($chunks) . ' chunks');
        $this->newLine();

        if ($dryRun) {
            $this->info('Sample chunks:');
            foreach (array_slice($chunks, 0, 3) as $i => $chunk) {
                $this->line("  Chunk {$i}: " . substr($chunk['text'], 0, 100) . '...');
            }
            $this->newLine();
            $this->info("Would upload " . count($chunks) . " chunks to Qdrant");
            return 0;
        }

        // Upload to Qdrant
        $this->info('🚀 Uploading to Qdrant...');
        $bar = $this->output->createProgressBar(count($chunks));
        $bar->start();

        $uploaded = 0;
        $errors = 0;
        $batchSize = 20;

        // Process in batches
        $batches = array_chunk($chunks, $batchSize);
        
        foreach ($batches as $batch) {
            $texts = array_column($batch, 'text');
            
            // Generate embeddings
            $embeddings = $this->embeddings->embedBatch($texts);
            
            // Build points
            $points = [];
            foreach ($batch as $index => $chunk) {
                if (isset($embeddings[$index]) && $embeddings[$index]) {
                    $points[] = [
                        'id' => $this->generatePointId($chunk['index']),
                        'vector' => $embeddings[$index],
                        'payload' => [
                            'type' => 'law_text',
                            'country_id' => $countryId,
                            'chunk_index' => $chunk['index'],
                            'text' => $chunk['text'],
                            'start_char' => $chunk['start'],
                            'end_char' => $chunk['end'],
                            'source' => basename($path),
                        ],
                    ];
                }
            }

            if (!empty($points)) {
                $result = $this->qdrant->upsertPoints($points);
                
                if ($result['success']) {
                    $uploaded += count($points);
                } else {
                    $errors += count($points);
                    Log::error('Failed to upload law chunks', ['error' => $result['message']]);
                }
            }

            $bar->advance(count($batch));
        }

        $bar->finish();
        $this->newLine();
        $this->newLine();

        $this->info("✅ Upload complete!");
        $this->info("   Uploaded: {$uploaded} chunks");
        if ($errors > 0) {
            $this->warn("   Errors: {$errors}");
        }

        // Show collection stats
        $this->showCollectionStats();

        return 0;
    }

    /**
     * Test connections
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

        $this->newLine();
        return true;
    }

    /**
     * Ensure collection exists
     */
    protected function ensureCollectionExists(): void
    {
        if (!$this->qdrant->collectionExists()) {
            $this->info("Creating collection...");
            $result = $this->qdrant->createCollection(null, $this->embeddings->getDimensions());
            
            if (!$result['success']) {
                $this->error('Failed to create collection: ' . $result['message']);
                return;
            }
            $this->info("✓ Collection created");
        } else {
            $this->info("✓ Collection exists");
        }
    }

    /**
     * Chunk text with overlap
     */
    protected function chunkText(string $text, int $chunkSize, int $overlap): array
    {
        $chunks = [];
        $position = 0;
        $index = 0;
        $textLength = strlen($text);

        while ($position < $textLength) {
            // Calculate end position
            $endPos = min($position + $chunkSize, $textLength);
            
            // Extract chunk
            $chunkText = substr($text, $position, $endPos - $position);
            
            // Try to break at a good point if not at end
            if ($endPos < $textLength) {
                // Look for last newline in chunk
                $lastNewline = strrpos($chunkText, "\n");
                if ($lastNewline !== false && $lastNewline > $chunkSize * 0.5) {
                    $chunkText = substr($chunkText, 0, $lastNewline);
                    $endPos = $position + $lastNewline;
                }
            }

            $chunkText = trim($chunkText);
            
            if (!empty($chunkText)) {
                $chunks[] = [
                    'index' => $index,
                    'text' => $chunkText,
                    'start' => $position,
                    'end' => $endPos,
                ];
                $index++;
            }

            // Move position forward (ensure we always advance)
            $newPosition = $endPos - $overlap;
            if ($newPosition <= $position) {
                $newPosition = $position + $chunkSize; // Force advance
            }
            $position = $newPosition;
        }

        return $chunks;
    }

    /**
     * Generate unique point ID
     */
    protected function generatePointId(int $chunkIndex): int
    {
        // Use prefix 5 for law text chunks
        return (int) (5 . str_pad($chunkIndex, 9, '0', STR_PAD_LEFT));
    }

    /**
     * Extract text from PDF using local parser (faster than API)
     */
    protected function extractPdfLocally(string $path): string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($path);
            return (string) $pdf->getText();
        } catch (\Throwable $e) {
            $this->error('PDF parsing failed: ' . $e->getMessage());
            return '';
        }
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
                    ['Vector Size', ($info['config']['params']['vectors']['size'] ?? 'N/A')],
                    ['Status', $info['status'] ?? 'Unknown'],
                ]
            );
        }
    }
}
