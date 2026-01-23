<?php

namespace App\Services;

use App\Models\CustomsCode;
use Illuminate\Support\Facades\Log;

class VectorClassifier
{
    protected QdrantClient $qdrant;
    protected EmbeddingService $embeddings;

    public function __construct(QdrantClient $qdrant, EmbeddingService $embeddings)
    {
        $this->qdrant = $qdrant;
        $this->embeddings = $embeddings;
    }

    /**
     * Get the count of points in the collection
     */
    public function getPointCount(): int
    {
        return $this->qdrant->countPoints();
    }

    /**
     * Check if the vector classifier is configured and ready
     */
    public function isReady(): bool
    {
        return $this->qdrant->isConfigured();
    }

    /**
     * Search for similar customs codes based on item description
     */
    public function searchSimilarCodes(string $itemDescription, ?int $countryId = null, int $limit = 10): array
    {
        // Generate embedding for the query
        $queryVector = $this->embeddings->embed($itemDescription);
        
        if (!$queryVector) {
            Log::warning('VectorClassifier: Failed to generate embedding for query', [
                'item' => $itemDescription,
            ]);
            return [
                'success' => false,
                'error' => 'Failed to generate embedding',
                'results' => [],
            ];
        }

        // Build filter for country if specified
        $filter = null;
        if ($countryId) {
            $filter = [
                'must' => [
                    ['key' => 'type', 'match' => ['value' => 'code']],
                    ['key' => 'country_id', 'match' => ['value' => $countryId]],
                ],
            ];
        } else {
            $filter = [
                'must' => [
                    ['key' => 'type', 'match' => ['value' => 'code']],
                ],
            ];
        }

        // Search Qdrant
        $searchResult = $this->qdrant->search($queryVector, $limit, $filter);

        if (!$searchResult['success']) {
            return [
                'success' => false,
                'error' => $searchResult['error'] ?? 'Search failed',
                'results' => [],
            ];
        }

        // Process results
        $results = [];
        foreach ($searchResult['results'] as $result) {
            $payload = $result['payload'] ?? [];
            $results[] = [
                'code' => $payload['code'] ?? null,
                'description' => $payload['description'] ?? null,
                'duty_rate' => $payload['duty_rate'] ?? null,
                'chapter_number' => $payload['chapter_number'] ?? null,
                'chapter_title' => $payload['chapter_title'] ?? null,
                'score' => round($result['score'] * 100, 2), // Convert to percentage
                'customs_code_id' => $payload['customs_code_id'] ?? null,
            ];
        }

        return [
            'success' => true,
            'results' => $results,
            'query' => $itemDescription,
        ];
    }

    /**
     * Search for relevant chapter notes
     */
    public function searchRelevantNotes(string $itemDescription, ?int $countryId = null, int $limit = 5): array
    {
        $queryVector = $this->embeddings->embed($itemDescription);
        
        if (!$queryVector) {
            return [
                'success' => false,
                'error' => 'Failed to generate embedding',
                'results' => [],
            ];
        }

        $filter = [
            'must' => [
                ['key' => 'type', 'match' => ['value' => 'note']],
            ],
        ];
        
        if ($countryId) {
            $filter['must'][] = ['key' => 'country_id', 'match' => ['value' => $countryId]];
        }

        $searchResult = $this->qdrant->search($queryVector, $limit, $filter);

        if (!$searchResult['success']) {
            return [
                'success' => false,
                'error' => $searchResult['error'] ?? 'Search failed',
                'results' => [],
            ];
        }

        $results = [];
        foreach ($searchResult['results'] as $result) {
            $payload = $result['payload'] ?? [];
            $results[] = [
                'chapter_number' => $payload['chapter_number'] ?? null,
                'note_type' => $payload['note_type'] ?? null,
                'note_text' => $payload['note_text'] ?? null,
                'score' => round($result['score'] * 100, 2),
            ];
        }

        return [
            'success' => true,
            'results' => $results,
        ];
    }

    /**
     * Search for relevant exclusion rules
     */
    public function searchExclusionRules(string $itemDescription, ?int $countryId = null, int $limit = 5): array
    {
        $queryVector = $this->embeddings->embed($itemDescription);
        
        if (!$queryVector) {
            return [
                'success' => false,
                'error' => 'Failed to generate embedding',
                'results' => [],
            ];
        }

        $filter = [
            'must' => [
                ['key' => 'type', 'match' => ['value' => 'exclusion']],
            ],
        ];
        
        if ($countryId) {
            $filter['must'][] = ['key' => 'country_id', 'match' => ['value' => $countryId]];
        }

        $searchResult = $this->qdrant->search($queryVector, $limit, $filter);

        if (!$searchResult['success']) {
            return [
                'success' => false,
                'error' => $searchResult['error'] ?? 'Search failed',
                'results' => [],
            ];
        }

        $results = [];
        foreach ($searchResult['results'] as $result) {
            $payload = $result['payload'] ?? [];
            $results[] = [
                'source_chapter' => $payload['source_chapter'] ?? null,
                'target_chapter' => $payload['target_chapter'] ?? null,
                'description_pattern' => $payload['description_pattern'] ?? null,
                'note_reference' => $payload['source_note_reference'] ?? null,
                'score' => round($result['score'] * 100, 2),
            ];
        }

        return [
            'success' => true,
            'results' => $results,
        ];
    }

    /**
     * Full classification with vector search
     * Returns top matches with context
     */
    public function classify(string $itemDescription, ?int $countryId = null): array
    {
        $startTime = microtime(true);
        
        // Search for similar codes
        $codesResult = $this->searchSimilarCodes($itemDescription, $countryId, 10);
        
        if (!$codesResult['success']) {
            return [
                'success' => false,
                'error' => $codesResult['error'],
                'time_ms' => round((microtime(true) - $startTime) * 1000),
            ];
        }

        // Get top match
        $topMatch = $codesResult['results'][0] ?? null;
        
        // Get alternative matches (different codes with good scores)
        $alternatives = [];
        $seenCodes = [];
        foreach ($codesResult['results'] as $result) {
            if ($result['code'] && !in_array($result['code'], $seenCodes)) {
                $seenCodes[] = $result['code'];
                if (count($alternatives) < 5 && $result['code'] !== ($topMatch['code'] ?? null)) {
                    $alternatives[] = [
                        'code' => $result['code'],
                        'description' => $result['description'],
                        'score' => $result['score'],
                    ];
                }
            }
        }

        // Calculate confidence based on score distribution
        $confidence = $this->calculateConfidence($codesResult['results']);

        return [
            'success' => true,
            'code' => $topMatch['code'] ?? null,
            'description' => $topMatch['description'] ?? null,
            'duty_rate' => $topMatch['duty_rate'] ?? null,
            'chapter' => $topMatch['chapter_title'] ?? null,
            'score' => $topMatch['score'] ?? 0,
            'confidence' => $confidence,
            'customs_code_id' => $topMatch['customs_code_id'] ?? null,
            'alternatives' => $alternatives,
            'all_matches' => $codesResult['results'],
            'time_ms' => round((microtime(true) - $startTime) * 1000),
        ];
    }

    /**
     * Calculate confidence based on score distribution
     */
    protected function calculateConfidence(array $results): int
    {
        if (empty($results)) {
            return 0;
        }

        $topScore = $results[0]['score'] ?? 0;
        
        // Base confidence on top score
        $confidence = min(100, $topScore);
        
        // If there's a big gap between #1 and #2, boost confidence
        if (count($results) >= 2) {
            $secondScore = $results[1]['score'] ?? 0;
            $gap = $topScore - $secondScore;
            
            if ($gap > 10) {
                $confidence = min(100, $confidence + 5);
            } elseif ($gap < 3) {
                // Very close scores indicate ambiguity
                $confidence = max(0, $confidence - 10);
            }
        }
        
        return (int) round($confidence);
    }

    /**
     * Verify a classification by checking if the suggested code matches vector search results
     */
    public function verifyClassification(string $itemDescription, string $suggestedCode, ?int $countryId = null): array
    {
        $searchResult = $this->searchSimilarCodes($itemDescription, $countryId, 20);
        
        if (!$searchResult['success']) {
            return [
                'verified' => false,
                'agreement' => false,
                'reason' => 'Vector search failed',
            ];
        }

        $results = $searchResult['results'];
        
        // Check if suggested code appears in top results
        $suggestedCodeNormalized = preg_replace('/[^0-9]/', '', $suggestedCode);
        $foundPosition = null;
        $foundScore = null;
        
        foreach ($results as $index => $result) {
            $resultCodeNormalized = preg_replace('/[^0-9]/', '', $result['code'] ?? '');
            
            // Check for exact match or prefix match
            if ($resultCodeNormalized === $suggestedCodeNormalized ||
                str_starts_with($resultCodeNormalized, $suggestedCodeNormalized) ||
                str_starts_with($suggestedCodeNormalized, $resultCodeNormalized)) {
                $foundPosition = $index + 1;
                $foundScore = $result['score'];
                break;
            }
        }

        // Determine verification status
        $topMatch = $results[0] ?? null;
        $topCode = $topMatch['code'] ?? null;
        $topScore = $topMatch['score'] ?? 0;
        
        if ($foundPosition === 1) {
            // Suggested code is the top match
            return [
                'verified' => true,
                'agreement' => true,
                'confidence' => 'high',
                'position' => 1,
                'score' => $foundScore,
                'message' => 'Vector search strongly agrees with classification',
            ];
        } elseif ($foundPosition !== null && $foundPosition <= 3) {
            // Suggested code is in top 3
            return [
                'verified' => true,
                'agreement' => true,
                'confidence' => 'medium',
                'position' => $foundPosition,
                'score' => $foundScore,
                'vector_top_match' => $topCode,
                'vector_top_score' => $topScore,
                'message' => "Classification found at position {$foundPosition} in vector results",
            ];
        } elseif ($foundPosition !== null && $foundPosition <= 10) {
            // Suggested code is in top 10
            return [
                'verified' => true,
                'agreement' => false,
                'confidence' => 'low',
                'position' => $foundPosition,
                'score' => $foundScore,
                'vector_top_match' => $topCode,
                'vector_top_score' => $topScore,
                'message' => "Classification found at position {$foundPosition}, consider alternative: {$topCode}",
            ];
        } else {
            // Suggested code not found in top results
            return [
                'verified' => false,
                'agreement' => false,
                'confidence' => 'none',
                'position' => null,
                'vector_top_match' => $topCode,
                'vector_top_score' => $topScore,
                'alternatives' => array_slice(array_map(fn($r) => $r['code'], $results), 0, 5),
                'message' => "Classification not found in vector search. Top suggestion: {$topCode}",
            ];
        }
    }

    /**
     * Get combined context for an item (codes + notes + exclusions)
     */
    public function getFullContext(string $itemDescription, ?int $countryId = null): array
    {
        // Run searches in sequence (could be parallelized with promises)
        $codes = $this->searchSimilarCodes($itemDescription, $countryId, 10);
        $notes = $this->searchRelevantNotes($itemDescription, $countryId, 5);
        $exclusions = $this->searchExclusionRules($itemDescription, $countryId, 3);

        return [
            'codes' => $codes['results'] ?? [],
            'notes' => $notes['results'] ?? [],
            'exclusions' => $exclusions['results'] ?? [],
        ];
    }

    /**
     * Test the vector classifier
     */
    public function test(string $testItem = "Frozen chicken wings"): array
    {
        $result = $this->classify($testItem);
        
        return [
            'test_item' => $testItem,
            'result' => $result,
        ];
    }
}
