<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    protected string $apiKey;
    protected string $model;
    protected int $dimensions;
    protected int $batchSize;

    public function __construct()
    {
        $this->apiKey = config('services.openai_embeddings.api_key') ?? config('services.openai.api_key');
        $this->model = config('services.openai_embeddings.model', 'text-embedding-3-small');
        $this->dimensions = config('services.openai_embeddings.dimensions', 1536);
        $this->batchSize = config('services.openai_embeddings.batch_size', 100);
    }

    /**
     * Generate embedding for a single text
     */
    public function embed(string $text, bool $useCache = true): ?array
    {
        if (empty(trim($text))) {
            return null;
        }

        // Check cache first
        if ($useCache) {
            $cacheKey = 'embedding_' . md5($text . $this->model);
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        $result = $this->embedBatch([$text], $useCache);
        return $result[0] ?? null;
    }

    /**
     * Generate embeddings for multiple texts (batch)
     */
    public function embedBatch(array $texts, bool $useCache = true): array
    {
        if (empty($texts)) {
            return [];
        }

        // Clean and validate texts
        $texts = array_map(function ($text) {
            return $this->cleanText($text);
        }, $texts);

        // Filter out empty texts but keep track of indices
        $validTexts = [];
        $validIndices = [];
        foreach ($texts as $index => $text) {
            if (!empty(trim($text))) {
                $validTexts[] = $text;
                $validIndices[] = $index;
            }
        }

        if (empty($validTexts)) {
            return array_fill(0, count($texts), null);
        }

        // Check cache for existing embeddings
        $embeddings = array_fill(0, count($texts), null);
        $textsToEmbed = [];
        $indicesToEmbed = [];

        if ($useCache) {
            foreach ($validTexts as $i => $text) {
                $cacheKey = 'embedding_' . md5($text . $this->model);
                $cached = Cache::get($cacheKey);
                if ($cached) {
                    $embeddings[$validIndices[$i]] = $cached;
                } else {
                    $textsToEmbed[] = $text;
                    $indicesToEmbed[] = $validIndices[$i];
                }
            }
        } else {
            $textsToEmbed = $validTexts;
            $indicesToEmbed = $validIndices;
        }

        if (empty($textsToEmbed)) {
            return $embeddings;
        }

        // Process in batches
        $batchResults = [];
        foreach (array_chunk($textsToEmbed, $this->batchSize) as $batchIndex => $batch) {
            $result = $this->callOpenAI($batch);
            if ($result) {
                $batchResults = array_merge($batchResults, $result);
            } else {
                // Fill with nulls if batch failed
                $batchResults = array_merge($batchResults, array_fill(0, count($batch), null));
            }
        }

        // Map results back to original indices and cache
        foreach ($batchResults as $i => $embedding) {
            if ($embedding !== null) {
                $originalIndex = $indicesToEmbed[$i];
                $embeddings[$originalIndex] = $embedding;
                
                if ($useCache) {
                    $cacheKey = 'embedding_' . md5($textsToEmbed[$i] . $this->model);
                    Cache::put($cacheKey, $embedding, now()->addDays(30));
                }
            }
        }

        return $embeddings;
    }

    /**
     * Call OpenAI Embeddings API
     */
    protected function callOpenAI(array $texts): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('OpenAI API key not configured for embeddings');
            return null;
        }

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(60)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $this->model,
                    'input' => $texts,
                    'dimensions' => $this->dimensions,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $embeddings = [];
                
                // Sort by index to ensure correct order
                $sortedData = collect($data['data'] ?? [])->sortBy('index')->values();
                
                foreach ($sortedData as $item) {
                    $embeddings[] = $item['embedding'] ?? null;
                }
                
                Log::debug('OpenAI embeddings generated', [
                    'count' => count($embeddings),
                    'model' => $this->model,
                    'usage' => $data['usage'] ?? null,
                ]);
                
                return $embeddings;
            }

            Log::error('OpenAI embeddings API failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            
            return null;
        } catch (\Exception $e) {
            Log::error('OpenAI embeddings API error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Clean text for embedding (remove excess whitespace, etc.)
     */
    protected function cleanText(string $text): string
    {
        // Replace multiple whitespace with single space
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim
        $text = trim($text);
        
        // Limit length (OpenAI has token limits)
        // text-embedding-3-small can handle ~8000 tokens, roughly 32000 chars
        if (strlen($text) > 30000) {
            $text = substr($text, 0, 30000);
        }
        
        return $text;
    }

    /**
     * Generate embedding for a customs code with its context
     */
    public function embedCustomsCode(array $codeData): ?array
    {
        $text = $this->buildCodeEmbeddingText($codeData);
        return $this->embed($text);
    }

    /**
     * Build text representation of a customs code for embedding
     */
    public function buildCodeEmbeddingText(array $codeData): string
    {
        $parts = [];
        
        // Code itself
        if (!empty($codeData['code'])) {
            $parts[] = "HS Code: {$codeData['code']}";
        }
        
        // Description (most important)
        if (!empty($codeData['description'])) {
            $parts[] = $codeData['description'];
        }
        
        // Chapter context
        if (!empty($codeData['chapter_title'])) {
            $parts[] = "Chapter: {$codeData['chapter_title']}";
        }
        
        // Keywords
        if (!empty($codeData['keywords']) && is_array($codeData['keywords'])) {
            $parts[] = "Keywords: " . implode(', ', $codeData['keywords']);
        }
        
        // Inclusion hints
        if (!empty($codeData['inclusion_hints'])) {
            $parts[] = "Includes: {$codeData['inclusion_hints']}";
        }
        
        return implode('. ', $parts);
    }

    /**
     * Build text representation of a chapter note for embedding
     */
    public function buildNoteEmbeddingText(array $noteData): string
    {
        $parts = [];
        
        if (!empty($noteData['chapter_number'])) {
            $parts[] = "Chapter {$noteData['chapter_number']} Note";
        }
        
        if (!empty($noteData['note_type'])) {
            $parts[] = "Type: {$noteData['note_type']}";
        }
        
        if (!empty($noteData['note_text'])) {
            $parts[] = $noteData['note_text'];
        }
        
        return implode('. ', $parts);
    }

    /**
     * Build text representation of an exclusion rule for embedding
     */
    public function buildExclusionEmbeddingText(array $exclusionData): string
    {
        $parts = [];
        
        $parts[] = "Exclusion Rule";
        
        if (!empty($exclusionData['description_pattern'])) {
            $parts[] = "Excludes: {$exclusionData['description_pattern']}";
        }
        
        if (!empty($exclusionData['source_chapter'])) {
            $parts[] = "From Chapter: {$exclusionData['source_chapter']}";
        }
        
        if (!empty($exclusionData['target_chapter'])) {
            $parts[] = "Redirect to Chapter: {$exclusionData['target_chapter']}";
        }
        
        if (!empty($exclusionData['source_note_reference'])) {
            $parts[] = "Reference: {$exclusionData['source_note_reference']}";
        }
        
        return implode('. ', $parts);
    }

    /**
     * Build text representation of an exemption for embedding
     */
    public function buildExemptionEmbeddingText(array $exemptionData): string
    {
        $parts = [];
        
        if (!empty($exemptionData['name'])) {
            $parts[] = "Exemption: {$exemptionData['name']}";
        }
        
        if (!empty($exemptionData['description'])) {
            $parts[] = $exemptionData['description'];
        }
        
        if (!empty($exemptionData['conditions']) && is_array($exemptionData['conditions'])) {
            $conditions = array_map(function ($c) {
                return $c['description'] ?? $c;
            }, $exemptionData['conditions']);
            $parts[] = "Conditions: " . implode('; ', $conditions);
        }
        
        if (!empty($exemptionData['applies_to_codes'])) {
            $parts[] = "Applies to: {$exemptionData['applies_to_codes']}";
        }
        
        return implode('. ', $parts);
    }

    /**
     * Get the configured dimensions
     */
    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * Get the configured model
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Clear embedding cache
     */
    public function clearCache(): void
    {
        // Note: This would need a more sophisticated cache implementation
        // to track all embedding cache keys. For now, cache will expire naturally.
        Log::info('Embedding cache clear requested - cache will expire naturally');
    }

    /**
     * Test the embedding service
     */
    public function test(): array
    {
        $testText = "Frozen chicken wings for human consumption";
        
        $embedding = $this->embed($testText, false);
        
        if ($embedding) {
            return [
                'success' => true,
                'message' => 'Embedding service working',
                'model' => $this->model,
                'dimensions' => count($embedding),
                'sample_text' => $testText,
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to generate embedding',
            'model' => $this->model,
        ];
    }
}
