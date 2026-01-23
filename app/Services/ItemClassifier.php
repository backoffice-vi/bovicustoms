<?php

namespace App\Services;

use App\Models\CustomsCode;
use App\Models\TariffChapter;
use App\Models\TariffChapterNote;
use App\Models\ClassificationExclusion;
use App\Models\ExemptionCategory;
use App\Models\ProhibitedGood;
use App\Models\RestrictedGood;
use App\Models\AdditionalLevy;
use App\Models\ClassificationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ItemClassifier
{
    protected string $apiKey;
    protected string $model;
    protected int $maxTokens;
    protected DocumentChunker $chunker;
    protected ?VectorClassifier $vectorClassifier = null;
    protected bool $useVectorVerification = true;
    protected bool $useVectorOnly = true; // Temporarily use Qdrant only, bypassing database/Claude

    public function __construct(DocumentChunker $chunker, ?VectorClassifier $vectorClassifier = null)
    {
        $this->apiKey = config('services.claude.api_key');
        $this->model = config('services.claude.model');
        $this->maxTokens = config('services.claude.max_tokens');
        $this->chunker = $chunker;
        $this->vectorClassifier = $vectorClassifier;
        $this->useVectorVerification = config('services.qdrant.api_key') ? true : false;
    }

    /**
     * Enable or disable vector verification
     */
    public function setVectorVerification(bool $enabled): self
    {
        $this->useVectorVerification = $enabled;
        return $this;
    }

    /**
     * Enable or disable vector-only mode (bypasses database/Claude)
     */
    public function setVectorOnly(bool $enabled): self
    {
        $this->useVectorOnly = $enabled;
        return $this;
    }

    /**
     * Classify an item using the 9-step algorithm
     */
    public function classify(string $itemDescription, ?int $countryId = null, ?int $organizationId = null): array
    {
        // Use vector-only mode if enabled and vector classifier is available
        if ($this->useVectorOnly && $this->vectorClassifier) {
            return $this->classifyWithVectorOnly($itemDescription, $countryId, $organizationId);
        }

        $classificationPath = [];
        
        try {
            // Step 1: Keyword Extraction
            $keywords = $this->extractKeywords($itemDescription);
            $classificationPath[] = "Extracted keywords: " . implode(', ', $keywords);
            
            Log::info('Classification started', [
                'item' => $itemDescription,
                'keywords' => $keywords,
                'country_id' => $countryId,
            ]);

            // Step 2: Prohibited/Restricted Check
            $prohibitedCheck = $this->checkProhibitedRestricted($itemDescription, $countryId);
            
            if ($prohibitedCheck['is_prohibited']) {
                return [
                    'success' => false,
                    'error' => 'Item is prohibited for import',
                    'prohibited' => true,
                    'prohibited_items' => $prohibitedCheck['prohibited_matches'],
                    'classification_path' => $classificationPath,
                ];
            }

            // Step 3: Candidate Selection
            $candidates = $this->findCandidateCodes($keywords, $itemDescription, $countryId);
            $classificationPath[] = "Found " . count($candidates) . " candidate codes";
            
            if ($candidates->isEmpty()) {
                // Try chapter-based classification when no codes match
                $classificationPath[] = "No codes found, trying chapter-based classification";
                
                $chapterResult = $this->classifyByChapter($keywords, $itemDescription, $countryId, $classificationPath);
                if ($chapterResult) {
                    return $chapterResult;
                }
                
                return [
                    'success' => false,
                    'error' => 'No matching customs codes found',
                    'classification_path' => $classificationPath,
                ];
            }

            // Get unique chapter IDs from candidates
            $candidateChapterIds = $candidates->pluck('tariff_chapter_id')->filter()->unique()->toArray();
            $classificationPath[] = "Candidate chapters: " . implode(', ', $candidateChapterIds);

            // Step 4: Exclusion Rules Check
            $exclusionResult = $this->applyExclusionRules($itemDescription, $candidateChapterIds, $countryId);
            
            if (!empty($exclusionResult['redirections'])) {
                $classificationPath = array_merge($classificationPath, $exclusionResult['redirections']);
                $candidateChapterIds = $exclusionResult['final_chapter_ids'];
                
                // Re-filter candidates based on allowed chapters
                $candidates = $candidates->filter(function ($code) use ($candidateChapterIds) {
                    return in_array($code->tariff_chapter_id, $candidateChapterIds);
                });
            }

            // Step 5: Build Focused Context
            $context = $this->buildFocusedContext($candidateChapterIds, $candidates, $countryId);
            $classificationPath[] = "Built context with " . count($context['notes']) . " notes and " . count($context['codes']) . " codes";

            // Step 5.5: Tenant approved reference precedents (prior approvals from imported declarations)
            $precedents = $this->findApprovedReferencePrecedents($itemDescription, $countryId);
            if (!empty($precedents)) {
                $classificationPath[] = "Found " . count($precedents) . " tenant reference precedents";
            }

            // Step 6: AI Classification
            $aiResult = $this->classifyWithClaude($itemDescription, $context, $classificationPath, $precedents);
            
            if (empty($aiResult['code'])) {
                return [
                    'success' => false,
                    'error' => 'AI could not determine classification',
                    'classification_path' => $classificationPath,
                ];
            }

            $classificationPath[] = "AI classified as: {$aiResult['code']} (confidence: {$aiResult['confidence']}%)";

            // Find the matched code in database
            $matchedCode = CustomsCode::where('code', $aiResult['code'])
                ->when($countryId, fn($q) => $q->where('country_id', $countryId))
                ->first();
            
            // If no exact match, find the most specific code that starts with AI's suggestion
            if (!$matchedCode && !empty($aiResult['code'])) {
                $aiCode = $aiResult['code'];
                $normalizedAiCode = str_replace('.', '', $aiCode);
                
                // Find codes that start with the AI's code
                $matchingCodes = CustomsCode::where(function ($q) use ($aiCode, $normalizedAiCode) {
                        $q->where('code', 'like', $aiCode . '%')
                          ->orWhere('code', 'like', $normalizedAiCode . '%');
                    })
                    ->when($countryId, fn($q) => $q->where('country_id', $countryId))
                    ->get();
                
                // Get the most specific (longest) matching code
                if ($matchingCodes->isNotEmpty()) {
                    $matchedCode = $matchingCodes->sortByDesc(function ($code) {
                        return strlen(str_replace('.', '', $code->code));
                    })->first();
                    
                    $classificationPath[] = "AI suggested {$aiCode}, found more specific code: {$matchedCode->code}";
                }
            }

            // Step 7: Exemption Check
            $exemptions = [];
            if ($matchedCode) {
                $exemptions = $matchedCode->getApplicableExemptions()->map(function ($exemption) {
                    return [
                        'id' => $exemption->id,
                        'name' => $exemption->name,
                        'description' => $exemption->description,
                        'legal_reference' => $exemption->legal_reference,
                        'conditions' => $exemption->conditions->map(function ($cond) {
                            return [
                                'type' => $cond->condition_type,
                                'description' => $cond->description,
                                'requirement' => $cond->requirement_text,
                                'mandatory' => $cond->is_mandatory,
                            ];
                        })->toArray(),
                    ];
                })->toArray();
            }

            // Step 8: Levy Calculation
            $dutyCalculation = null;
            if ($matchedCode) {
                $dutyCalculation = $matchedCode->calculateTotalDuty(100, 1); // Base calculation on $100
            }

            // Step 8.5: Vector Verification (parallel search using Qdrant)
            $vectorVerification = null;
            if ($this->useVectorVerification && $this->vectorClassifier && !empty($aiResult['code'])) {
                try {
                    $vectorVerification = $this->vectorClassifier->verifyClassification(
                        $itemDescription,
                        $aiResult['code'],
                        $countryId
                    );
                    
                    if ($vectorVerification) {
                        $classificationPath[] = "Vector verification: " . ($vectorVerification['message'] ?? 'completed');
                        
                        // Adjust confidence based on vector agreement
                        if ($vectorVerification['agreement'] ?? false) {
                            $aiResult['confidence'] = min(100, $aiResult['confidence'] + 5);
                        } elseif (($vectorVerification['confidence'] ?? '') === 'none') {
                            $aiResult['confidence'] = max(50, $aiResult['confidence'] - 10);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Vector verification failed', ['error' => $e->getMessage()]);
                    $classificationPath[] = "Vector verification: skipped (error)";
                }
            }

            // Step 9: Return Result
            return [
                'success' => true,
                'item' => $itemDescription,
                'code' => $aiResult['code'],
                'description' => $aiResult['description'] ?? $matchedCode?->description,
                'duty_rate' => $matchedCode?->duty_rate ?? $aiResult['duty_rate'],
                'unit_of_measurement' => $matchedCode?->formatted_unit,
                'special_rate' => $matchedCode?->special_rate,
                'confidence' => $aiResult['confidence'],
                'explanation' => $aiResult['explanation'],
                'alternatives' => $aiResult['alternatives'] ?? [],
                'classification_path' => $classificationPath,
                'customs_code_id' => $matchedCode?->id,
                'chapter' => $matchedCode?->tariffChapter?->formatted_identifier,
                // Prohibited/Restricted status
                'restricted' => $prohibitedCheck['is_restricted'],
                'restricted_items' => $prohibitedCheck['restricted_matches'] ?? [],
                // Exemptions
                'exemptions_available' => $exemptions,
                // Duty calculation
                'duty_calculation' => $dutyCalculation,
                // Vector verification results
                'vector_verification' => $vectorVerification,
            ];

        } catch (\Exception $e) {
            Log::error('Item classification failed', [
                'item' => $itemDescription,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'classification_path' => $classificationPath,
            ];
        }
    }

    /**
     * Step 1: Extract keywords from item description
     */
    protected function extractKeywords(string $description): array
    {
        $stopWords = [
            'the', 'of', 'and', 'or', 'a', 'an', 'in', 'to', 'for', 'with', 
            'as', 'at', 'by', 'on', 'is', 'are', 'was', 'were', 'be', 'been',
            'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
            'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need',
            'this', 'that', 'these', 'those', 'it', 'its', 'new', 'used', 'other'
        ];

        // Extract words
        preg_match_all('/\b([a-z]{3,})\b/i', strtolower($description), $matches);
        $words = array_diff($matches[1] ?? [], $stopWords);
        $words = array_unique($words);

        return array_values(array_slice($words, 0, 15));
    }

    /**
     * Use AI to expand keywords to tariff-relevant terms
     */
    protected function expandKeywordsWithAI(string $itemDescription, array $keywords): array
    {
        $keywordList = implode(', ', $keywords);
        
        $prompt = <<<PROMPT
Given this item description, provide additional keywords that would help find the correct tariff chapter.

ITEM: {$itemDescription}
CURRENT KEYWORDS: {$keywordList}

Think about:
- What CATEGORY does this belong to? (e.g., chicken → meat, poultry, animal)
- What MATERIAL is it made of? (e.g., wooden table → wood, furniture)
- What is it used for? (e.g., tractor → vehicle, agricultural, machinery)

Return ONLY a JSON array of 3-6 additional keywords that might appear in tariff chapter titles:
["keyword1", "keyword2", "keyword3"]

Examples:
- "frozen chicken wings" → ["meat", "poultry", "animal", "edible"]
- "laptop computer" → ["machine", "electronic", "data", "processing"]
- "cotton t-shirt" → ["apparel", "clothing", "textile", "garment"]
PROMPT;

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(15)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => 100,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);

            if ($response->successful()) {
                $aiResponse = $response->json()['content'][0]['text'] ?? '';
                
                // Parse JSON array from response
                if (preg_match('/\[.*\]/s', $aiResponse, $matches)) {
                    $expanded = json_decode($matches[0], true);
                    if (is_array($expanded)) {
                        return array_map('strtolower', $expanded);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Keyword expansion failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Classify by chapter when no specific codes are found
     */
    protected function classifyByChapter(array $keywords, string $itemDescription, ?int $countryId, array &$classificationPath): ?array
    {
        if (!$countryId) {
            return null;
        }

        // Step 1: Use AI to expand keywords to tariff-relevant terms
        $expandedKeywords = $this->expandKeywordsWithAI($itemDescription, $keywords);
        $classificationPath[] = "AI expanded keywords: " . implode(', ', $expandedKeywords);
        
        $allKeywords = array_unique(array_merge($keywords, $expandedKeywords));

        // Step 2: Search chapters by keywords in TITLES
        $chapters = TariffChapter::where('country_id', $countryId)
            ->where(function ($q) use ($allKeywords) {
                foreach ($allKeywords as $kw) {
                    $q->orWhere('title', 'like', '%' . $kw . '%');
                }
            })
            ->get();

        // Step 3: Also search chapter NOTES for keywords
        $notesQuery = TariffChapterNote::whereHas('chapter', function ($q) use ($countryId) {
                $q->where('country_id', $countryId);
            })
            ->where(function ($q) use ($allKeywords) {
                foreach ($allKeywords as $kw) {
                    $q->orWhere('note_text', 'like', '%' . $kw . '%');
                }
            })
            ->with('chapter')
            ->get();
        
        // Get chapters from notes that matched
        $chaptersFromNotes = $notesQuery->pluck('chapter')->filter()->unique('id');
        
        // Merge chapters from title search and notes search
        $allChapters = $chapters->merge($chaptersFromNotes)->unique('id');
        
        // Step 4: If no chapters found, try searching codes directly by expanded keywords
        if ($allChapters->isEmpty()) {
            $classificationPath[] = "No matching chapters found, searching codes directly by keywords";
            
            // Search codes directly using expanded keywords
            $directCodeMatches = CustomsCode::where('country_id', $countryId)
                ->where(function ($q) use ($allKeywords) {
                    foreach ($allKeywords as $kw) {
                        $q->orWhere('description', 'like', '%' . $kw . '%');
                    }
                })
                ->orderBy('code')
                ->get();
            
            if ($directCodeMatches->isNotEmpty()) {
                $classificationPath[] = "Found " . $directCodeMatches->count() . " codes matching keywords";
                
                // Use AI to select the best code from direct matches
                $codesContext = "";
                foreach ($directCodeMatches as $code) {
                    $rate = $code->duty_rate !== null ? " (Duty: {$code->duty_rate}%)" : "";
                    $codesContext .= "- {$code->code}: {$code->description}{$rate}\n";
                }

                $prompt = <<<PROMPT
You are a customs classification expert. Select the most appropriate HS code for this item from the available codes.

ITEM TO CLASSIFY: "{$itemDescription}"

AVAILABLE CODES:
{$codesContext}

INSTRUCTIONS:
1. Choose the MOST SPECIFIC code that matches the item
2. You MUST select one of the codes listed above - do not invent codes
3. Consider the item's primary function and composition
4. Return the EXACT code from the list above

Return ONLY a JSON object (no other text):
{
    "code": "2204.21",
    "description": "Brief description of why this code fits",
    "confidence": 90,
    "explanation": "Detailed explanation of the classification"
}
PROMPT;

                try {
                    $response = Http::withoutVerifying()
                        ->withHeaders([
                            'x-api-key' => $this->apiKey,
                            'anthropic-version' => '2023-06-01',
                            'Content-Type' => 'application/json',
                        ])
                        ->timeout(30)
                        ->post('https://api.anthropic.com/v1/messages', [
                            'model' => $this->model,
                            'max_tokens' => 500,
                            'messages' => [['role' => 'user', 'content' => $prompt]],
                        ]);

                    if ($response->successful()) {
                        $aiResponse = $response->json()['content'][0]['text'] ?? '';
                        
                        if (preg_match('/\{.*\}/s', $aiResponse, $matches)) {
                            $result = json_decode($matches[0], true);
                            
                            if ($result && isset($result['code'])) {
                                $aiCode = $result['code'];
                                
                                // Try exact match first
                                $matchedCode = $directCodeMatches->firstWhere('code', $aiCode);
                                
                                // If no exact match, find most specific code starting with AI's suggestion
                                if (!$matchedCode) {
                                    $normalizedAiCode = str_replace('.', '', $aiCode);
                                    $matchingCodes = $directCodeMatches->filter(function ($code) use ($normalizedAiCode, $aiCode) {
                                        $normalizedDbCode = str_replace('.', '', $code->code);
                                        return str_starts_with($normalizedDbCode, $normalizedAiCode) 
                                            || str_starts_with($code->code, $aiCode);
                                    });
                                    
                                    if ($matchingCodes->isNotEmpty()) {
                                        $matchedCode = $matchingCodes->sortByDesc(function ($code) {
                                            return strlen(str_replace('.', '', $code->code));
                                        })->first();
                                    }
                                }
                                
                                if ($matchedCode) {
                                    $classificationPath[] = "AI selected code: {$matchedCode->code}";
                                    
                                    return [
                                        'success' => true,
                                        'item' => $itemDescription,
                                        'code' => $matchedCode->code,
                                        'description' => $result['description'] ?? $matchedCode->description,
                                        'duty_rate' => $matchedCode->duty_rate,
                                        'unit_of_measurement' => $matchedCode->formatted_unit,
                                        'special_rate' => $matchedCode->special_rate,
                                        'confidence' => $result['confidence'] ?? 85,
                                        'explanation' => $result['explanation'] ?? '',
                                        'chapter' => $matchedCode->tariffChapter?->formatted_identifier ?? "Chapter " . substr($matchedCode->code, 0, 2),
                                        'classification_path' => $classificationPath,
                                        'is_chapter_level' => false,
                                        'alternatives' => [],
                                        'customs_code_id' => $matchedCode->id,
                                        'restricted' => false,
                                        'restricted_items' => [],
                                        'exemptions_available' => $matchedCode->getApplicableExemptions()->toArray(),
                                        'duty_calculation' => $matchedCode->calculateTotalDuty(100, 1),
                                    ];
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Direct code search AI call failed', ['error' => $e->getMessage()]);
                }
            }
            
            $classificationPath[] = "No matching codes found";
            return null;
        }

        $classificationPath[] = "Found " . $allChapters->count() . " matching chapters (titles: " . $chapters->count() . ", notes: " . $chaptersFromNotes->count() . ")";

        // Get chapter numbers to search for codes
        $chapterNumbers = $allChapters->pluck('chapter_number')->toArray();
        
        // Step 4: Find ALL codes in these chapters from the database
        $codesInChapters = CustomsCode::where('country_id', $countryId)
            ->where(function ($q) use ($chapterNumbers) {
                foreach ($chapterNumbers as $chNum) {
                    // Match codes that start with the chapter number (e.g., "22" matches "2204.21")
                    $q->orWhere('code', 'like', $chNum . '%');
                }
            })
            ->orderBy('code')
            ->get();
        
        $classificationPath[] = "Found " . $codesInChapters->count() . " codes in matched chapters";

        // If we found codes in the chapters, use AI to pick the best one
        if ($codesInChapters->isNotEmpty()) {
            $codesContext = "";
            foreach ($codesInChapters as $code) {
                $rate = $code->duty_rate !== null ? " (Duty: {$code->duty_rate}%)" : "";
                $codesContext .= "- {$code->code}: {$code->description}{$rate}\n";
            }

            $prompt = <<<PROMPT
You are a customs classification expert. Select the most appropriate HS code for this item from the available codes.

ITEM TO CLASSIFY: "{$itemDescription}"

AVAILABLE CODES:
{$codesContext}

INSTRUCTIONS:
1. Choose the MOST SPECIFIC code that matches the item
2. You MUST select one of the codes listed above - do not invent codes
3. Consider the item's primary function and composition

Return ONLY a JSON object (no other text):
{
    "code": "2204.21",
    "description": "Brief description of why this code fits",
    "confidence": 90,
    "explanation": "Detailed explanation of the classification"
}
PROMPT;

            try {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'x-api-key' => $this->apiKey,
                        'anthropic-version' => '2023-06-01',
                        'Content-Type' => 'application/json',
                    ])
                    ->timeout(30)
                    ->post('https://api.anthropic.com/v1/messages', [
                        'model' => $this->model,
                        'max_tokens' => 500,
                        'messages' => [['role' => 'user', 'content' => $prompt]],
                    ]);

                if ($response->successful()) {
                    $aiResponse = $response->json()['content'][0]['text'] ?? '';
                    
                    if (preg_match('/\{.*\}/s', $aiResponse, $matches)) {
                        $result = json_decode($matches[0], true);
                        
                        if ($result && isset($result['code'])) {
                            $aiCode = $result['code'];
                            
                            // First try exact match
                            $matchedCode = $codesInChapters->firstWhere('code', $aiCode);
                            
                            // If no exact match, find the most specific code that starts with the AI's suggestion
                            if (!$matchedCode) {
                                // Normalize the code (remove dots for comparison)
                                $normalizedAiCode = str_replace('.', '', $aiCode);
                                
                                // Find codes that start with the AI's code
                                $matchingCodes = $codesInChapters->filter(function ($code) use ($normalizedAiCode, $aiCode) {
                                    $normalizedDbCode = str_replace('.', '', $code->code);
                                    return str_starts_with($normalizedDbCode, $normalizedAiCode) 
                                        || str_starts_with($code->code, $aiCode);
                                });
                                
                                // Get the most specific (longest) matching code
                                if ($matchingCodes->isNotEmpty()) {
                                    $matchedCode = $matchingCodes->sortByDesc(function ($code) {
                                        return strlen(str_replace('.', '', $code->code));
                                    })->first();
                                    
                                    $classificationPath[] = "AI suggested {$aiCode}, found more specific code: {$matchedCode->code}";
                                }
                            }
                            
                            if ($matchedCode) {
                                $classificationPath[] = "AI selected code: {$matchedCode->code} (confidence: " . ($result['confidence'] ?? 'N/A') . "%)";
                                
                                return [
                                    'success' => true,
                                    'item' => $itemDescription,
                                    'code' => $matchedCode->code,
                                    'description' => $result['description'] ?? $matchedCode->description,
                                    'duty_rate' => $matchedCode->duty_rate,
                                    'unit_of_measurement' => $matchedCode->formatted_unit,
                                    'special_rate' => $matchedCode->special_rate,
                                    'confidence' => $result['confidence'] ?? 85,
                                    'explanation' => $result['explanation'] ?? '',
                                    'chapter' => $matchedCode->tariffChapter?->formatted_identifier ?? "Chapter " . substr($matchedCode->code, 0, 2),
                                    'classification_path' => $classificationPath,
                                    'is_chapter_level' => false,
                                    'alternatives' => [],
                                    'customs_code_id' => $matchedCode->id,
                                    'restricted' => false,
                                    'restricted_items' => [],
                                    'exemptions_available' => $matchedCode->getApplicableExemptions()->toArray(),
                                    'duty_calculation' => $matchedCode->calculateTotalDuty(100, 1),
                                ];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Chapter code selection AI call failed', ['error' => $e->getMessage()]);
            }
        }

        // Fallback: No codes found in chapters, return chapter-level result
        // Get all notes for matched chapters
        $chapterIds = $allChapters->pluck('id')->toArray();
        $notes = TariffChapterNote::whereIn('tariff_chapter_id', $chapterIds)->get();

        // Build context for AI
        $context = "Based on the chapter titles and notes, classify this item.\n\n";
        $context .= "MATCHING CHAPTERS:\n";
        foreach ($allChapters as $ch) {
            $context .= "- Chapter {$ch->chapter_number}: {$ch->title}\n";
        }
        
        if ($notes->isNotEmpty()) {
            $context .= "\nCHAPTER NOTES:\n";
            foreach ($notes->take(10) as $note) {
                $ch = $allChapters->firstWhere('id', $note->tariff_chapter_id);
                if ($ch) {
                    $context .= "Ch {$ch->chapter_number}: " . substr($note->note_text, 0, 200) . "\n";
                }
            }
        }

        // Use AI to determine best chapter and suggest a proper HS code format
        $prompt = <<<PROMPT
Based on the following chapter information, determine the most likely tariff classification for this item.

ITEM: {$itemDescription}

{$context}

IMPORTANT: You must return a proper HS code format (e.g., "2204" or "2204.21"), NOT "Chapter XX".

Return JSON:
{
    "code": "2204",
    "chapter_number": "22",
    "chapter_title": "Beverages, spirits and vinegar",
    "confidence": 75,
    "explanation": "Why this classification is appropriate. Note that only the chapter/heading level can be determined without specific codes in the database."
}
PROMPT;

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => 500,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);

            if ($response->successful()) {
                $aiResponse = $response->json()['content'][0]['text'] ?? '';
                
                // Parse JSON from response
                if (preg_match('/\{.*\}/s', $aiResponse, $matches)) {
                    $result = json_decode($matches[0], true);
                    
                    if ($result && isset($result['code'])) {
                        // Ensure the code is not in "Chapter XX" format
                        $code = $result['code'];
                        if (stripos($code, 'chapter') !== false) {
                            // Extract just the number
                            $code = preg_replace('/[^0-9.]/', '', $code);
                        }
                        
                        $classificationPath[] = "AI suggested code: " . $code . " (confidence: " . ($result['confidence'] ?? 'N/A') . "%)";
                        
                        return [
                            'success' => true,
                            'item' => $itemDescription,
                            'code' => $code,
                            'description' => $result['explanation'] ?? '',
                            'duty_rate' => null,
                            'unit_of_measurement' => null,
                            'special_rate' => null,
                            'confidence' => $result['confidence'] ?? 70,
                            'explanation' => $result['explanation'] ?? '',
                            'chapter' => $result['chapter_title'] ?? "Chapter " . ($result['chapter_number'] ?? substr($code, 0, 2)),
                            'note' => 'This is a heading-level classification. Specific subheading codes are not available in the database.',
                            'classification_path' => $classificationPath,
                            'is_chapter_level' => true,
                            'alternatives' => [],
                            'customs_code_id' => null,
                            'restricted' => false,
                            'restricted_items' => [],
                            'exemptions_available' => [],
                            'duty_calculation' => [
                                'base_duty_rate' => 'Varies by subheading',
                                'note' => 'Heading-level classification - specific duty rate depends on exact product code',
                            ],
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Chapter classification AI call failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Step 2: Check if item is prohibited or restricted
     */
    protected function checkProhibitedRestricted(string $description, ?int $countryId): array
    {
        $result = [
            'is_prohibited' => false,
            'is_restricted' => false,
            'prohibited_matches' => [],
            'restricted_matches' => [],
        ];

        if (!$countryId) {
            return $result;
        }

        // Check prohibited goods
        $prohibitedMatches = ProhibitedGood::findMatching($description, $countryId);
        if ($prohibitedMatches->isNotEmpty()) {
            $result['is_prohibited'] = true;
            $result['prohibited_matches'] = $prohibitedMatches->map(function ($item) {
                return [
                    'name' => $item->name,
                    'description' => $item->description,
                    'legal_reference' => $item->legal_reference,
                ];
            })->toArray();
        }

        // Check restricted goods
        $restrictedMatches = RestrictedGood::findMatching($description, $countryId);
        if ($restrictedMatches->isNotEmpty()) {
            $result['is_restricted'] = true;
            $result['restricted_matches'] = $restrictedMatches->map(function ($item) {
                return [
                    'name' => $item->name,
                    'description' => $item->description,
                    'restriction_type' => $item->restriction_type,
                    'permit_authority' => $item->permit_authority,
                    'requirements' => $item->requirements,
                ];
            })->toArray();
        }

        return $result;
    }

    /**
     * Step 3: Find candidate codes by keywords and description
     */
    protected function findCandidateCodes(array $keywords, string $description, ?int $countryId)
    {
        $query = CustomsCode::query();

        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        // Search by classification_keywords JSON field
        $query->where(function ($q) use ($keywords, $description) {
            // Match by stored keywords
            foreach ($keywords as $keyword) {
                $q->orWhereJsonContains('classification_keywords', $keyword);
            }
            
            // Also search description
            foreach ($keywords as $keyword) {
                $q->orWhere('description', 'like', "%{$keyword}%");
            }
        });

        return $query->with('tariffChapter')
            ->orderBy('code')
            ->limit(500)
            ->get();
    }

    /**
     * Step 4: Apply exclusion rules and redirect chapters
     */
    protected function applyExclusionRules(string $description, array $chapterIds, ?int $countryId): array
    {
        $redirections = [];
        $excludedChapters = [];
        $addedChapters = [];

        if (!$countryId || empty($chapterIds)) {
            return [
                'redirections' => [],
                'final_chapter_ids' => $chapterIds,
            ];
        }

        foreach ($chapterIds as $chapterId) {
            // Find exclusion rules for this chapter
            $exclusions = ClassificationExclusion::where('source_chapter_id', $chapterId)
                ->where('country_id', $countryId)
                ->orderBy('priority', 'desc')
                ->get();

            foreach ($exclusions as $exclusion) {
                if ($exclusion->matchesText($description)) {
                    $excludedChapters[] = $chapterId;
                    
                    $sourceChapter = TariffChapter::find($chapterId);
                    $targetChapter = $exclusion->targetChapter;
                    
                    $redirections[] = "Exclusion rule applied: {$sourceChapter?->formatted_identifier} → " .
                        ($targetChapter?->formatted_identifier ?? $exclusion->target_heading ?? 'other') .
                        " (per {$exclusion->source_note_reference})";
                    
                    if ($exclusion->target_chapter_id) {
                        $addedChapters[] = $exclusion->target_chapter_id;
                    }
                    
                    break; // Only apply first matching rule per chapter
                }
            }
        }

        // Remove excluded chapters, add target chapters
        $finalChapterIds = array_diff($chapterIds, $excludedChapters);
        $finalChapterIds = array_unique(array_merge($finalChapterIds, $addedChapters));

        return [
            'redirections' => $redirections,
            'final_chapter_ids' => array_values($finalChapterIds),
        ];
    }

    /**
     * Step 5: Build focused context for AI
     */
    protected function buildFocusedContext(array $chapterIds, $candidates, ?int $countryId): array
    {
        $notes = [];
        $codes = [];

        // Get notes for relevant chapters
        foreach ($chapterIds as $chapterId) {
            $chapter = TariffChapter::with(['notes', 'section.notes'])->find($chapterId);
            
            if ($chapter) {
                // Add section notes
                if ($chapter->section) {
                    foreach ($chapter->section->notes as $note) {
                        $notes[] = [
                            'type' => 'section',
                            'reference' => $note->formatted_reference,
                            'text' => $note->note_text,
                        ];
                    }
                }
                
                // Add chapter notes
                foreach ($chapter->notes as $note) {
                    $notes[] = [
                        'type' => 'chapter',
                        'reference' => $note->formatted_reference,
                        'text' => $note->note_text,
                    ];
                }
            }
        }

        // Format candidate codes
        foreach ($candidates as $code) {
            $codes[] = [
                'code' => $code->code,
                'description' => $code->description,
                'duty_rate' => $code->duty_rate,
                'unit' => $code->formatted_unit,
                'hints' => $code->inclusion_hints,
            ];
        }

        return [
            'notes' => $notes,
            'codes' => $codes,
        ];
    }

    /**
     * Step 6: Classify with Claude AI
     */
    protected function classifyWithClaude(string $itemDescription, array $context, array &$classificationPath, array $precedents = []): array
    {
        $prompt = $this->buildClassificationPrompt($itemDescription, $context, $precedents);
        $estimatedTokens = $this->chunker->estimateTokens($prompt);
        
        $classificationPath[] = "AI prompt: {$estimatedTokens} estimated tokens";

        // If too large, limit codes
        if ($estimatedTokens > 90000) {
            $context['codes'] = array_slice($context['codes'], 0, 200);
            $prompt = $this->buildClassificationPrompt($itemDescription, $context, []);
            $classificationPath[] = "Reduced context to 200 codes due to token limit";
        }

        $response = Http::withoutVerifying()
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Claude API request failed: ' . $response->body());
        }

        $result = $response->json();
        $content = $result['content'][0]['text'] ?? '';

        return $this->parseClassificationResponse($content);
    }

    /**
     * Build the classification prompt with notes context
     */
    protected function buildClassificationPrompt(string $itemDescription, array $context, array $precedents = []): string
    {
        // Format notes
        $notesText = '';
        foreach ($context['notes'] as $note) {
            $notesText .= "### {$note['reference']}\n{$note['text']}\n\n";
        }

        // Format tenant precedents (approved trade declarations)
        $precedentText = '';
        if (!empty($precedents)) {
            $precedentText .= "## TENANT APPROVED REFERENCE (PRIOR APPROVALS)\n";
            $precedentText .= "These are real prior approvals in this tenant. Use them as precedent if relevant.\n\n";
            foreach ($precedents as $p) {
                $hs = $p['hs_code'] ?? '';
                $desc = $p['description'] ?? '';
                $precedentText .= "- {$hs}: {$desc}\n";
            }
            $precedentText .= "\n";
        }

        // Format codes
        $codesText = '';
        foreach ($context['codes'] as $code) {
            $rate = $code['duty_rate'] !== null ? " (Duty: {$code['duty_rate']}%)" : "";
            $unit = $code['unit'] ? " [{$code['unit']}]" : "";
            $hints = $code['hints'] ? " - {$code['hints']}" : "";
            $codesText .= "- {$code['code']}: {$code['description']}{$rate}{$unit}{$hints}\n";
        }

        return <<<PROMPT
You are a customs classification expert. Classify the following item based on the Harmonized System (HS) codes and chapter notes provided.

## IMPORTANT NOTES AND RULES
These notes contain critical rules for classification. Read them carefully:

{$notesText}

{$precedentText}

## ITEM TO CLASSIFY
"{$itemDescription}"

## AVAILABLE TARIFF CODES
{$codesText}

## INSTRUCTIONS
1. Read the chapter notes carefully - they contain exclusions and definitions
2. Apply any relevant rules from the notes (e.g., if notes say "does not cover X", don't classify X under this chapter)
3. Find the most specific code that matches the item
4. Consider the item's primary function and composition

## RESPONSE FORMAT
Return a JSON object:
{
    "code": "8417.10",
    "description": "Brief description of why this code fits",
    "duty_rate": 15,
    "confidence": 85,
    "explanation": "Detailed explanation citing any relevant notes",
    "alternatives": ["8417.20", "8418.10"]
}

Only return the JSON object, no other text.
PROMPT;
    }

    /**
     * Pull a few recent tenant-approved HS code precedents from imported declarations.
     */
    protected function findApprovedReferencePrecedents(string $itemDescription, ?int $countryId): array
    {
        if (!$countryId || !auth()->check()) {
            return [];
        }

        $keywords = $this->extractKeywords($itemDescription);
        if (empty($keywords)) {
            return [];
        }

        $candidates = \App\Models\DeclarationFormItem::query()
            ->where('country_id', $countryId)
            ->whereNotNull('hs_code')
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get();

        $scored = [];
        foreach ($candidates as $c) {
            $desc = strtolower((string) $c->description);
            $hits = 0;
            foreach ($keywords as $kw) {
                if ($kw && str_contains($desc, strtolower($kw))) {
                    $hits++;
                }
            }

            if ($hits > 0) {
                $scored[] = [
                    'score' => $hits,
                    'hs_code' => (string) $c->hs_code,
                    'description' => (string) $c->description,
                ];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, 8);

        return array_map(fn ($r) => [
            'hs_code' => $r['hs_code'],
            'description' => $r['description'],
        ], $top);
    }

    /**
     * Parse AI classification response
     */
    protected function parseClassificationResponse(string $content): array
    {
        $content = trim($content);

        // Extract JSON from code blocks
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        $result = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse AI classification response', [
                'error' => json_last_error_msg(),
                'content' => substr($content, 0, 500),
            ]);
            return [];
        }

        return [
            'code' => $result['code'] ?? null,
            'description' => $result['description'] ?? null,
            'duty_rate' => $result['duty_rate'] ?? null,
            'confidence' => (int) ($result['confidence'] ?? 0),
            'explanation' => $result['explanation'] ?? 'No explanation provided',
            'alternatives' => $result['alternatives'] ?? [],
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
     * Apply custom classification rules
     */
    protected function applyClassificationRules(string $itemDescription, ?int $organizationId = null, ?int $countryId = null): ?array
    {
        try {
            // Get applicable rules ordered by priority
            $rules = ClassificationRule::getApplicableRules($itemDescription, $organizationId, $countryId);
            
            if (empty($rules)) {
                return null;
            }

            // Return the highest priority matching rule
            $rule = $rules[0];
            
            return [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'rule_type' => $rule->rule_type,
                'target_code' => $rule->target_code,
                'instruction' => $rule->instruction,
                'priority' => $rule->priority,
            ];

        } catch (\Exception $e) {
            Log::warning('Error applying classification rules', [
                'error' => $e->getMessage(),
                'item' => $itemDescription,
            ]);
            return null;
        }
    }

    /**
     * Get all instructions for AI context
     */
    protected function getClassificationInstructions(?int $organizationId = null, ?int $countryId = null): string
    {
        $instructions = ClassificationRule::getInstructions($organizationId, $countryId);
        
        if (empty($instructions)) {
            return '';
        }

        return "Custom classification instructions:\n" . implode("\n", array_map(fn($i) => "- {$i}", $instructions));
    }

    /**
     * Test classification with verbose output
     */
    public function testClassification(string $itemDescription, ?int $countryId = null): array
    {
        $result = $this->classify($itemDescription, $countryId);
        
        return [
            'input' => $itemDescription,
            'result' => $result,
            'debug' => [
                'country_id' => $countryId,
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Classify using Qdrant vector search only (bypasses database/Claude)
     */
    protected function classifyWithVectorOnly(string $itemDescription, ?int $countryId = null, ?int $organizationId = null): array
    {
        $classificationPath = ['Using Qdrant vector-only classification'];
        
        try {
            // Step 0: Check for custom classification rules
            $ruleResult = $this->applyClassificationRules($itemDescription, $organizationId, $countryId);
            if ($ruleResult) {
                $classificationPath[] = "Custom rule applied: {$ruleResult['rule_name']}";
                
                // If rule specifies a target code, use it directly
                if (!empty($ruleResult['target_code'])) {
                    $matchedCode = CustomsCode::where('code', 'like', $ruleResult['target_code'] . '%')
                        ->when($countryId, fn($q) => $q->where('country_id', $countryId))
                        ->first();

                    if ($matchedCode) {
                        $classificationPath[] = "Rule assigned code: {$matchedCode->code}";
                        
                        return [
                            'success' => true,
                            'item' => $itemDescription,
                            'code' => $matchedCode->code,
                            'description' => $matchedCode->description,
                            'duty_rate' => $matchedCode->duty_rate,
                            'unit_of_measurement' => $matchedCode->formatted_unit,
                            'special_rate' => $matchedCode->special_rate,
                            'confidence' => 95, // High confidence for rule-based
                            'explanation' => "Classified by custom rule: {$ruleResult['rule_name']}. " . ($ruleResult['instruction'] ?? ''),
                            'alternatives' => [],
                            'classification_path' => $classificationPath,
                            'customs_code_id' => $matchedCode->id,
                            'chapter' => $matchedCode->tariffChapter?->formatted_identifier,
                            'restricted' => false,
                            'restricted_items' => [],
                            'exemptions_available' => $matchedCode->getApplicableExemptions()->toArray(),
                            'duty_calculation' => $matchedCode->calculateTotalDuty(100, 1),
                            'source' => 'rule',
                            'rule_applied' => $ruleResult,
                            'vector_score' => null,
                            'is_ambiguous' => false,
                            'ambiguity_note' => null,
                            'all_matches' => [],
                        ];
                    }
                }
                
                // Rule has instruction but no target code - add to context for explanation
                $classificationPath[] = "Rule instruction: {$ruleResult['instruction']}";
            }
            
            // Step 1: Check prohibited/restricted (still use database for safety)
            $prohibitedCheck = $this->checkProhibitedRestricted($itemDescription, $countryId);
            
            if ($prohibitedCheck['is_prohibited']) {
                return [
                    'success' => false,
                    'error' => 'Item is prohibited for import',
                    'prohibited' => true,
                    'prohibited_items' => $prohibitedCheck['prohibited_matches'],
                    'classification_path' => $classificationPath,
                ];
            }

            // Step 2: Vector search for similar codes
            $vectorResult = $this->vectorClassifier->classify($itemDescription, $countryId);
            $classificationPath[] = "Vector search completed in {$vectorResult['time_ms']}ms";
            
            if (!$vectorResult['success'] || empty($vectorResult['code'])) {
                return [
                    'success' => false,
                    'error' => 'No matching codes found in vector search',
                    'classification_path' => $classificationPath,
                ];
            }

            $classificationPath[] = "Top match: {$vectorResult['code']} (score: {$vectorResult['score']}%)";

            // Step 3: Get the matched code from database for additional info
            $matchedCode = CustomsCode::where('code', $vectorResult['code'])
                ->when($countryId, fn($q) => $q->where('country_id', $countryId))
                ->first();

            // Step 4: Build alternatives list with context
            $alternatives = $vectorResult['alternatives'] ?? [];
            
            // Determine if this is an ambiguous classification
            $isAmbiguous = false;
            $ambiguityNote = null;
            
            if (!empty($alternatives)) {
                $topScore = $vectorResult['score'] ?? 0;
                $secondScore = $alternatives[0]['score'] ?? 0;
                
                // If top 2 scores are close (within 10%), mark as ambiguous
                if ($topScore - $secondScore < 10) {
                    $isAmbiguous = true;
                    $ambiguityNote = "This item could fall under multiple categories. Please review the alternatives below to select the most appropriate classification.";
                }
            }

            // Step 5: Get exemptions if we have a matched code
            $exemptions = [];
            if ($matchedCode) {
                $exemptions = $matchedCode->getApplicableExemptions()->map(function ($exemption) {
                    return [
                        'id' => $exemption->id,
                        'name' => $exemption->name,
                        'description' => $exemption->description,
                    ];
                })->toArray();
            }

            // Step 6: Calculate duty if we have a matched code
            $dutyCalculation = null;
            if ($matchedCode) {
                $dutyCalculation = $matchedCode->calculateTotalDuty(100, 1);
            }

            // Build explanation
            $explanation = "Classification determined by semantic similarity search. ";
            $explanation .= "The item description was compared against {$this->vectorClassifier->getPointCount()} tariff entries. ";
            $explanation .= "Top match score: {$vectorResult['score']}%.";
            
            if ($isAmbiguous) {
                $explanation .= " Note: Multiple categories have similar relevance scores.";
            }

            return [
                'success' => true,
                'item' => $itemDescription,
                'code' => $vectorResult['code'],
                'description' => $vectorResult['description'] ?? $matchedCode?->description,
                'duty_rate' => $vectorResult['duty_rate'] ?? $matchedCode?->duty_rate,
                'unit_of_measurement' => $matchedCode?->formatted_unit,
                'special_rate' => $matchedCode?->special_rate,
                'confidence' => $vectorResult['confidence'] ?? 85,
                'explanation' => $explanation,
                'alternatives' => $alternatives,
                'classification_path' => $classificationPath,
                'customs_code_id' => $matchedCode?->id ?? $vectorResult['customs_code_id'],
                'chapter' => $vectorResult['chapter'] ?? $matchedCode?->tariffChapter?->formatted_identifier,
                // Prohibited/Restricted status
                'restricted' => $prohibitedCheck['is_restricted'],
                'restricted_items' => $prohibitedCheck['restricted_matches'] ?? [],
                // Exemptions
                'exemptions_available' => $exemptions,
                // Duty calculation
                'duty_calculation' => $dutyCalculation,
                // Vector-specific fields
                'source' => 'vector',
                'vector_score' => $vectorResult['score'],
                'is_ambiguous' => $isAmbiguous,
                'ambiguity_note' => $ambiguityNote,
                'all_matches' => $vectorResult['all_matches'] ?? [],
            ];

        } catch (\Exception $e) {
            Log::error('Vector-only classification failed', [
                'item' => $itemDescription,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Vector classification failed: ' . $e->getMessage(),
                'classification_path' => $classificationPath,
            ];
        }
    }
}
