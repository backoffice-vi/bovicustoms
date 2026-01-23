<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QdrantClient
{
    protected ?string $url;
    protected ?string $apiKey;
    protected ?string $collection;
    protected int $timeout;

    public function __construct()
    {
        $this->url = config('services.qdrant.url');
        $this->apiKey = config('services.qdrant.api_key');
        $this->collection = config('services.qdrant.collection');
        $this->timeout = config('services.qdrant.timeout', 30);
    }

    /**
     * Check if Qdrant is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->url) && !empty($this->apiKey) && !empty($this->collection);
    }

    /**
     * Get HTTP client with authentication
     */
    protected function client()
    {
        return Http::withoutVerifying()
            ->withHeaders([
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout);
    }

    /**
     * Test connection to Qdrant
     */
    public function testConnection(): array
    {
        try {
            $response = $this->client()->get($this->url);
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connected to Qdrant',
                    'data' => $response->json(),
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to connect: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * List all collections
     */
    public function listCollections(): array
    {
        try {
            $response = $this->client()->get("{$this->url}/collections");
            
            if ($response->successful()) {
                return $response->json()['result']['collections'] ?? [];
            }
            
            Log::error('Qdrant listCollections failed', ['response' => $response->body()]);
            return [];
        } catch (\Exception $e) {
            Log::error('Qdrant listCollections error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Check if collection exists
     */
    public function collectionExists(?string $collectionName = null): bool
    {
        $name = $collectionName ?? $this->collection;
        
        try {
            $response = $this->client()->get("{$this->url}/collections/{$name}");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a collection with specified vector dimensions
     */
    public function createCollection(?string $collectionName = null, int $dimensions = 1536): array
    {
        $name = $collectionName ?? $this->collection;
        
        try {
            $response = $this->client()->put("{$this->url}/collections/{$name}", [
                'vectors' => [
                    'size' => $dimensions,
                    'distance' => 'Cosine',
                ],
                'optimizers_config' => [
                    'default_segment_number' => 2,
                ],
                'replication_factor' => 1,
            ]);
            
            if ($response->successful()) {
                Log::info("Qdrant collection '{$name}' created", ['dimensions' => $dimensions]);
                
                // Create required payload indexes for filtering
                $this->createPayloadIndex('type', 'keyword', $name);
                $this->createPayloadIndex('country_id', 'integer', $name);
                
                return [
                    'success' => true,
                    'message' => "Collection '{$name}' created successfully",
                ];
            }
            
            Log::error('Qdrant createCollection failed', ['response' => $response->body()]);
            return [
                'success' => false,
                'message' => 'Failed to create collection: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Qdrant createCollection error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create a payload index for filtering
     */
    public function createPayloadIndex(string $fieldName, string $fieldSchema, ?string $collectionName = null): array
    {
        $name = $collectionName ?? $this->collection;
        
        try {
            $response = $this->client()->put("{$this->url}/collections/{$name}/index", [
                'field_name' => $fieldName,
                'field_schema' => $fieldSchema,
            ]);
            
            if ($response->successful()) {
                Log::info("Qdrant payload index created", [
                    'collection' => $name,
                    'field' => $fieldName,
                    'schema' => $fieldSchema,
                ]);
                return ['success' => true];
            }
            
            Log::warning("Qdrant createPayloadIndex failed", ['response' => $response->body()]);
            return ['success' => false, 'message' => $response->body()];
        } catch (\Exception $e) {
            Log::warning("Qdrant createPayloadIndex error", ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Delete a collection
     */
    public function deleteCollection(?string $collectionName = null): array
    {
        $name = $collectionName ?? $this->collection;
        
        try {
            $response = $this->client()->delete("{$this->url}/collections/{$name}");
            
            if ($response->successful()) {
                Log::info("Qdrant collection '{$name}' deleted");
                return [
                    'success' => true,
                    'message' => "Collection '{$name}' deleted successfully",
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to delete collection: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Qdrant deleteCollection error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get collection info
     */
    public function getCollectionInfo(?string $collectionName = null): ?array
    {
        $name = $collectionName ?? $this->collection;
        
        try {
            $response = $this->client()->get("{$this->url}/collections/{$name}");
            
            if ($response->successful()) {
                return $response->json()['result'] ?? null;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Qdrant getCollectionInfo error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Upsert points (vectors with payloads)
     * 
     * @param array $points Array of ['id' => string|int, 'vector' => array, 'payload' => array]
     */
    public function upsertPoints(array $points, ?string $collectionName = null): array
    {
        $name = $collectionName ?? $this->collection;
        
        if (empty($points)) {
            return ['success' => true, 'message' => 'No points to upsert'];
        }
        
        try {
            $response = $this->client()
                ->timeout(60) // Longer timeout for batch operations
                ->put("{$this->url}/collections/{$name}/points", [
                    'points' => $points,
                ]);
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Upserted ' . count($points) . ' points',
                    'count' => count($points),
                ];
            }
            
            Log::error('Qdrant upsertPoints failed', [
                'response' => $response->body(),
                'point_count' => count($points),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to upsert points: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Qdrant upsertPoints error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Search for similar vectors
     * 
     * @param array $vector The query vector
     * @param int $limit Number of results to return
     * @param array|null $filter Optional filter conditions
     */
    public function search(array $vector, int $limit = 10, ?array $filter = null, ?string $collectionName = null): array
    {
        $name = $collectionName ?? $this->collection;
        
        $body = [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
        ];
        
        if ($filter) {
            $body['filter'] = $filter;
        }
        
        try {
            $response = $this->client()->post("{$this->url}/collections/{$name}/points/search", $body);
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'results' => $response->json()['result'] ?? [],
                ];
            }
            
            Log::error('Qdrant search failed', ['response' => $response->body()]);
            return [
                'success' => false,
                'results' => [],
                'error' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Qdrant search error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'results' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Search with filter by type
     */
    public function searchByType(array $vector, string $type, int $limit = 10, ?int $countryId = null): array
    {
        $filter = [
            'must' => [
                ['key' => 'type', 'match' => ['value' => $type]],
            ],
        ];
        
        if ($countryId) {
            $filter['must'][] = ['key' => 'country_id', 'match' => ['value' => $countryId]];
        }
        
        return $this->search($vector, $limit, $filter);
    }

    /**
     * Search only customs codes
     */
    public function searchCodes(array $vector, int $limit = 10, ?int $countryId = null): array
    {
        return $this->searchByType($vector, 'code', $limit, $countryId);
    }

    /**
     * Delete points by filter
     */
    public function deleteByFilter(array $filter, ?string $collectionName = null): array
    {
        $name = $collectionName ?? $this->collection;
        
        try {
            $response = $this->client()->post("{$this->url}/collections/{$name}/points/delete", [
                'filter' => $filter,
            ]);
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Points deleted successfully',
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Failed to delete points: ' . $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Qdrant deleteByFilter error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete all points for a specific country
     */
    public function deleteByCountry(int $countryId, ?string $collectionName = null): array
    {
        return $this->deleteByFilter([
            'must' => [
                ['key' => 'country_id', 'match' => ['value' => $countryId]],
            ],
        ], $collectionName);
    }

    /**
     * Count points in collection
     */
    public function countPoints(?string $collectionName = null): int
    {
        $info = $this->getCollectionInfo($collectionName);
        return $info['points_count'] ?? 0;
    }

    /**
     * Get the configured collection name
     */
    public function getCollectionName(): ?string
    {
        return $this->collection;
    }
}
