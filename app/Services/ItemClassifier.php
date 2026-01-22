<?php

namespace App\Services;

use App\Models\CustomsCode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ItemClassifier
{
    protected string $apiKey;
    protected string $model;
    protected int $maxTokens;

    public function __construct()
    {
        $this->apiKey = config('services.claude.api_key');
        $this->model = config('services.claude.model');
        $this->maxTokens = config('services.claude.max_tokens');
    }

    /**
     * Classify an item and find the best matching customs code
     */
    public function classify(string $itemName, ?int $countryId = null): array
    {
        try {
            // Get all customs codes (cached)
            $customsCodes = $this->getCustomsCodes($countryId);

            if ($customsCodes->isEmpty()) {
                return [
                    'success' => false,
                    'error' => 'No customs codes available for classification',
                ];
            }

            // Build prompt and send to Claude
            $result = $this->classifyWithClaude($itemName, $customsCodes);

            return [
                'success' => true,
                'item' => $itemName,
                'match' => $result,
            ];

        } catch (\Exception $e) {
            Log::error('Item classification failed', [
                'item' => $itemName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get customs codes from cache or database
     */
    protected function getCustomsCodes(?int $countryId = null): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = 'customs_codes_' . ($countryId ?? 'all');
        
        return Cache::remember($cacheKey, 3600, function () use ($countryId) {
            $query = CustomsCode::query();
            
            if ($countryId) {
                $query->where('country_id', $countryId);
            }
            
            return $query->select('id', 'code', 'description', 'duty_rate', 'country_id')
                        ->orderBy('code')
                        ->get();
        });
    }

    /**
     * Clear the customs codes cache
     */
    public function clearCache(?int $countryId = null): void
    {
        if ($countryId) {
            Cache::forget('customs_codes_' . $countryId);
        }
        Cache::forget('customs_codes_all');
    }

    /**
     * Classify item using Claude API
     */
    protected function classifyWithClaude(string $itemName, $customsCodes): array
    {
        $prompt = $this->buildClassificationPrompt($itemName, $customsCodes);

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Claude API request failed: ' . $response->body());
        }

        $result = $response->json();
        $content = $result['content'][0]['text'] ?? '';

        return $this->parseClassificationResponse($content, $customsCodes);
    }

    /**
     * Build the classification prompt
     */
    protected function buildClassificationPrompt(string $itemName, $customsCodes): string
    {
        // Build categories list
        $categoriesList = $customsCodes->map(function ($code) {
            $rate = $code->duty_rate !== null ? " (Duty: {$code->duty_rate}%)" : "";
            return "- {$code->code}: {$code->description}{$rate}";
        })->implode("\n");

        return <<<PROMPT
You are a customs classification expert. Given an item description, determine the most appropriate tariff/customs code from the available categories.

ITEM TO CLASSIFY: "{$itemName}"

AVAILABLE CATEGORIES:
{$categoriesList}

Analyze the item and find the best matching category. Consider:
1. What the item actually is (even if described informally)
2. Its primary use or purpose
3. The material it's made of (if relevant)
4. Industry classification standards

Return your response as a JSON object with:
- "code": The best matching tariff code
- "description": The category description
- "confidence": A number from 0-100 indicating confidence level
- "explanation": Brief explanation of why this category was chosen
- "alternative_codes": Array of up to 2 other possible codes if uncertain (can be empty)

Example response:
{
    "code": "8703",
    "description": "Motor cars and other motor vehicles principally designed for the transport of persons",
    "confidence": 95,
    "explanation": "A 'car' is a motor vehicle designed for passenger transport, which falls under HS code 8703.",
    "alternative_codes": ["8704"]
}

IMPORTANT: Only return the JSON object, no other text. If no suitable category exists, return confidence of 0 and explain why.
PROMPT;
    }

    /**
     * Parse Claude's classification response
     */
    protected function parseClassificationResponse(string $content, $customsCodes): array
    {
        $content = trim($content);
        
        // Try to extract JSON if wrapped in markdown code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        $result = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse Claude classification response', [
                'error' => json_last_error_msg(),
                'content' => substr($content, 0, 500),
            ]);
            throw new \Exception('Failed to parse AI response');
        }

        // Validate and enhance the result
        $matchedCode = $customsCodes->firstWhere('code', $result['code'] ?? '');
        
        return [
            'code' => $result['code'] ?? null,
            'description' => $result['description'] ?? null,
            'duty_rate' => $matchedCode?->duty_rate,
            'confidence' => (int) ($result['confidence'] ?? 0),
            'explanation' => $result['explanation'] ?? 'No explanation provided',
            'alternative_codes' => $result['alternative_codes'] ?? [],
            'customs_code_id' => $matchedCode?->id,
        ];
    }

    /**
     * Bulk classify multiple items
     */
    public function classifyBulk(array $items, ?int $countryId = null): array
    {
        $results = [];
        
        foreach ($items as $item) {
            $results[] = $this->classify($item, $countryId);
        }
        
        return $results;
    }
}
