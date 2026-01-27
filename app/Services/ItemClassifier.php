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
    protected bool $useVectorOnly = false; // Use Qdrant only, bypassing database/Claude
    protected bool $useHybridMode = true; // Use Qdrant for candidates, Claude for reasoning (recommended)

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
        if ($enabled) {
            $this->useHybridMode = false; // Vector-only takes precedence
        }
        return $this;
    }

    /**
     * Enable or disable hybrid mode (Qdrant candidates + Claude reasoning)
     */
    public function setHybridMode(bool $enabled): self
    {
        $this->useHybridMode = $enabled;
        if ($enabled) {
            $this->useVectorOnly = false; // Hybrid mode takes precedence over vector-only
        }
        return $this;
    }

    /**
     * Classify an item using tiered search:
     * Tier 1: Exact code match (if user types a code)
     * Tier 2: Database keyword search (fast, free)
     * Tier 3: Qdrant + Claude (only if database search fails)
     */
    public function classify(string $itemDescription, ?int $countryId = null, ?int $organizationId = null): array
    {
        $classificationPath = ['Using tiered classification approach'];
        
        // TIER 1: Check if input is an exact code (e.g., "02.07" or "0207")
        $exactCodeResult = $this->tryExactCodeMatch($itemDescription, $countryId);
        if ($exactCodeResult) {
            $classificationPath[] = "Tier 1: Exact code match found";
            $exactCodeResult['classification_path'] = $classificationPath;
            return $exactCodeResult;
        }
        
        // TIER 2: Database keyword search (the original 9-step algorithm)
        $classificationPath[] = "Tier 2: Attempting database keyword search";
        $databaseResult = $this->classifyWithDatabaseSearch($itemDescription, $countryId, $organizationId, $classificationPath);
        
        if ($databaseResult && $databaseResult['success']) {
            $classificationPath[] = "Tier 2: Database search successful";
            $databaseResult['classification_path'] = $classificationPath;
            return $databaseResult;
        }
        
        // TIER 3: Fall back to Qdrant + Claude if database search fails
        if ($this->vectorClassifier) {
            $classificationPath[] = "Tier 3: Falling back to Qdrant + Claude";
            
            if ($this->useHybridMode) {
                $result = $this->classifyWithVectorAndClaude($itemDescription, $countryId, $organizationId);
            } elseif ($this->useVectorOnly) {
                $result = $this->classifyWithVectorOnly($itemDescription, $countryId, $organizationId);
            } else {
                $result = $this->classifyWithVectorAndClaude($itemDescription, $countryId, $organizationId);
            }
            
            // IMPORTANT: Always look up rates from database, not Qdrant
            if ($result['success'] && !empty($result['code'])) {
                $result = $this->enrichResultFromDatabase($result, $countryId);
            }
            
            $result['classification_path'] = array_merge($classificationPath, $result['classification_path'] ?? []);
            return $result;
        }
        
        // No results from any tier
        return [
            'success' => false,
            'error' => 'Could not classify item - no matches found in database or vector search',
            'classification_path' => $classificationPath,
        ];
    }
    
    /**
     * Tier 1: Try exact code match (user typed a code directly)
     */
    protected function tryExactCodeMatch(string $input, ?int $countryId): ?array
    {
        // Clean input - remove spaces, check if it looks like a code
        $cleanInput = trim($input);
        
        // Check if input looks like an HS code (e.g., "02.07", "0207", "8703.10")
        if (!preg_match('/^[\d\.]+$/', $cleanInput)) {
            return null; // Not a code, it's a description
        }
        
        // Try exact match
        $code = CustomsCode::where('code', $cleanInput)
            ->when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->first();
        
        // Try with dots removed
        if (!$code) {
            $withoutDots = str_replace('.', '', $cleanInput);
            $code = CustomsCode::where(function($q) use ($cleanInput, $withoutDots) {
                $q->where('code', 'like', $cleanInput . '%')
                  ->orWhereRaw("REPLACE(code, '.', '') LIKE ?", [$withoutDots . '%']);
            })
            ->when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->orderByRaw('LENGTH(code) DESC')
            ->first();
        }
        
        if (!$code) {
            return null;
        }
        
        return [
            'success' => true,
            'item' => $input,
            'code' => $code->code,
            'description' => $code->description,
            'duty_rate' => $code->duty_rate,
            'unit_of_measurement' => $code->formatted_unit,
            'special_rate' => $code->special_rate,
            'confidence' => 100,
            'explanation' => "Direct code lookup - exact match found in database. This code is for: {$code->description}",
            'alternatives' => [],
            'customs_code_id' => $code->id,
            'chapter' => $code->tariffChapter?->formatted_identifier,
            'restricted' => false,
            'restricted_items' => [],
            'exemptions_available' => $code->getApplicableExemptions()->toArray(),
            'duty_calculation' => $code->calculateTotalDuty(100, 1),
            'source' => 'exact_match',
        ];
    }
    
    /**
     * Tier 2: Database keyword search (original algorithm)
     */
    protected function classifyWithDatabaseSearch(string $itemDescription, ?int $countryId, ?int $organizationId, array &$classificationPath): ?array
    {
        $internalPath = [];
        
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
                    // Enrich with database rates
                    return $this->enrichResultFromDatabase($chapterResult, $countryId);
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
                // No code from AI - try chapter-based search with keyword expansion
                $classificationPath[] = "AI couldn't classify from candidates, trying expanded search";
                $chapterResult = $this->classifyByChapter($keywords, $itemDescription, $countryId, $classificationPath);
                if ($chapterResult) {
                    // Enrich with database rates
                    return $this->enrichResultFromDatabase($chapterResult, $countryId);
                }
                
                return [
                    'success' => false,
                    'error' => 'AI could not determine classification',
                    'classification_path' => $classificationPath,
                ];
            }
            
            // Check AI status field for non-success responses
            $aiStatus = $aiResult['status'] ?? 'success';
            $needsFallback = in_array($aiStatus, ['insufficient', 'ambiguous', 'not_found']);
            
            // Also check for legacy pattern responses (backward compatibility)
            if (!$needsFallback) {
                $unablePatterns = ['UNABLE_TO_CLASSIFY', 'CANNOT_CLASSIFY', 'NOT_FOUND', 'NO_MATCH', 'CLASSIFICATION_NOT_POSSIBLE', 'NOT_POSSIBLE', 'INSUFFICIENT'];
                foreach ($unablePatterns as $pattern) {
                    if (stripos($aiResult['code'], $pattern) !== false) {
                        $needsFallback = true;
                        break;
                    }
                }
            }
            
            if ($needsFallback) {
                // AI says candidates don't match - try expanded keyword search
                $classificationPath[] = "AI status: '{$aiStatus}' - trying expanded keyword search";
                $chapterResult = $this->classifyByChapter($keywords, $itemDescription, $countryId, $classificationPath);
                if ($chapterResult) {
                    // Enrich with database rates
                    return $this->enrichResultFromDatabase($chapterResult, $countryId);
                }
                
                // Still no match - this is a genuine failure
                return [
                    'success' => false,
                    'error' => "Item '{$itemDescription}' cannot be classified under the available codes. AI noted: " . ($aiResult['explanation'] ?? 'No explanation'),
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

            // Step 9: Build alternatives from database candidates
            $alternatives = $this->buildAlternativesFromCandidates(
                $candidates ?? collect(),
                $aiResult['code'],
                $aiResult['alternatives'] ?? [],
                5 // max alternatives
            );

            // Step 10: Return Result
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
                'alternatives' => $alternatives,
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
     * Enrich Qdrant/Claude results with authoritative database data
     * IMPORTANT: Rates must ALWAYS come from the database, not Qdrant
     */
    protected function enrichResultFromDatabase(array $result, ?int $countryId): array
    {
        if (empty($result['code'])) {
            return $result;
        }
        
        // Look up the main code in database (exact match first)
        $mainCode = CustomsCode::where('code', $result['code'])
            ->when($countryId, fn($q) => $q->where('country_id', $countryId))
            ->first();
        
        // If no exact match, try to find a parent/similar code
        if (!$mainCode) {
            $searchCode = $result['code'];
            $normalizedCode = str_replace('.', '', $searchCode);
            
            // Try finding codes that start with the suggested code prefix
            $matchingCodes = CustomsCode::where(function($q) use ($searchCode, $normalizedCode) {
                    $q->where('code', 'like', substr($searchCode, 0, 4) . '%')
                      ->orWhereRaw("REPLACE(code, '.', '') LIKE ?", [substr($normalizedCode, 0, 4) . '%']);
                })
                ->when($countryId, fn($q) => $q->where('country_id', $countryId))
                ->get();
            
            if ($matchingCodes->isNotEmpty()) {
                // Find the best match (longest code that is a prefix of or matches the suggested code)
                $mainCode = $matchingCodes->sortByDesc(function($code) use ($normalizedCode) {
                    $dbCode = str_replace('.', '', $code->code);
                    // Score by how much of the suggested code matches
                    $matchLen = 0;
                    for ($i = 0; $i < min(strlen($dbCode), strlen($normalizedCode)); $i++) {
                        if ($dbCode[$i] === $normalizedCode[$i]) {
                            $matchLen++;
                        } else {
                            break;
                        }
                    }
                    return $matchLen * 100 + strlen($dbCode);
                })->first();
                
                // Update the result code to the actual database code
                if ($mainCode) {
                    $result['code'] = $mainCode->code;
                }
            }
        }
        
        if ($mainCode) {
            // Override with database values (source of truth)
            $result['duty_rate'] = $mainCode->duty_rate;
            $result['description'] = $mainCode->description;
            $result['unit_of_measurement'] = $mainCode->formatted_unit;
            $result['special_rate'] = $mainCode->special_rate;
            $result['customs_code_id'] = $mainCode->id;
            $result['chapter'] = $mainCode->tariffChapter?->formatted_identifier;
            $result['exemptions_available'] = $mainCode->getApplicableExemptions()->toArray();
            $result['duty_calculation'] = $mainCode->calculateTotalDuty(100, 1);
        }
        
        // Enrich alternatives with database rates
        if (!empty($result['alternatives'])) {
            $result['alternatives'] = array_map(function($alt) use ($countryId) {
                if (empty($alt['code'])) {
                    return $alt;
                }
                
                $altCode = CustomsCode::where('code', $alt['code'])
                    ->when($countryId, fn($q) => $q->where('country_id', $countryId))
                    ->first();
                
                if ($altCode) {
                    $alt['duty_rate'] = $altCode->duty_rate;
                    $alt['description'] = $altCode->description;
                    $alt['customs_code_id'] = $altCode->id;
                }
                
                return $alt;
            }, $result['alternatives']);
        }
        
        return $result;
    }

    /**
     * Build alternatives from database candidates
     * Prioritizes codes in the same heading/section as the selected code
     */
    protected function buildAlternativesFromCandidates(
        $candidates,
        string $selectedCode,
        array $aiAlternatives = [],
        int $maxAlternatives = 5
    ): array {
        $alternatives = [];
        
        // Normalize selected code for comparison
        $normalizedSelected = str_replace('.', '', $selectedCode);
        
        // Extract heading prefix (first 4 digits) for prioritization
        $selectedHeading = substr($normalizedSelected, 0, 4);
        $selectedChapter = substr($normalizedSelected, 0, 2);
        
        // First, add any alternatives Claude suggested (if they exist in candidates)
        foreach ($aiAlternatives as $altCode) {
            if (is_string($altCode) && count($alternatives) < $maxAlternatives) {
                $normalizedAlt = str_replace('.', '', $altCode);
                
                // Find this code in candidates
                $match = $candidates->first(function($c) use ($altCode, $normalizedAlt) {
                    $dbCode = str_replace('.', '', $c->code);
                    return $c->code === $altCode || $dbCode === $normalizedAlt;
                });
                
                if ($match && str_replace('.', '', $match->code) !== $normalizedSelected) {
                    $alternatives[] = [
                        'code' => $match->code,
                        'description' => $match->description,
                        'duty_rate' => $match->duty_rate,
                        'customs_code_id' => $match->id,
                    ];
                }
            }
        }
        
        $addedCodes = array_column($alternatives, 'code');
        $addedCodes[] = $selectedCode;
        
        // Sort candidates by relevance: same heading > same chapter > others
        $sortedCandidates = $candidates->sortBy(function($candidate) use ($selectedHeading, $selectedChapter, $normalizedSelected) {
            $normalizedCandidate = str_replace('.', '', $candidate->code);
            $candidateHeading = substr($normalizedCandidate, 0, 4);
            $candidateChapter = substr($normalizedCandidate, 0, 2);
            
            // Priority: 0 = same heading, 1 = same chapter, 2 = other
            if ($candidateHeading === $selectedHeading) {
                return '0_' . $candidate->code;
            } elseif ($candidateChapter === $selectedChapter) {
                return '1_' . $candidate->code;
            }
            return '2_' . $candidate->code;
        });
        
        // Then add other candidates from sorted list
        foreach ($sortedCandidates as $candidate) {
            if (count($alternatives) >= $maxAlternatives) {
                break;
            }
            
            $normalizedCandidate = str_replace('.', '', $candidate->code);
            
            // Skip if already added or is the selected code
            $isAlreadyAdded = false;
            foreach ($addedCodes as $added) {
                if ($candidate->code === $added || $normalizedCandidate === str_replace('.', '', $added)) {
                    $isAlreadyAdded = true;
                    break;
                }
            }
            
            if (!$isAlreadyAdded) {
                $alternatives[] = [
                    'code' => $candidate->code,
                    'description' => $candidate->description,
                    'duty_rate' => $candidate->duty_rate,
                    'customs_code_id' => $candidate->id,
                ];
                $addedCodes[] = $candidate->code;
            }
        }
        
        return $alternatives;
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
    "status": "success",
    "code": "2204.21",
    "description": "Brief description of why this code fits",
    "confidence": 90,
    "explanation": "Detailed explanation of the classification"
}

STATUS VALUES:
- "success" = Found a matching code
- "insufficient" = Item doesn't match any provided codes
- "ambiguous" = Multiple codes could apply equally
- "not_found" = Cannot determine classification
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
                                    
                                    // Build alternatives from other candidates
                                    $alternatives = $this->buildAlternativesFromCandidates(
                                        $directCodeMatches,
                                        $matchedCode->code,
                                        [],
                                        5
                                    );
                                    
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
                                        'alternatives' => $alternatives,
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
            // Prioritize codes from title-matched chapters over note-matched chapters
            $titleMatchedChapterNumbers = $chapters->pluck('chapter_number')->toArray();
            
            // Sort codes: title-matched chapters first, then by code
            $sortedCodes = $codesInChapters->sortBy(function($code) use ($titleMatchedChapterNumbers) {
                $chapterNum = (int) substr(str_replace('.', '', $code->code), 0, 2);
                $isTitleMatch = in_array($chapterNum, $titleMatchedChapterNumbers) ? 0 : 1;
                return $isTitleMatch . '_' . $code->code;
            });
            
            // Limit codes sent to AI to avoid token issues
            $codesToSend = $sortedCodes->take(100);
            
            $codesContext = "";
            foreach ($codesToSend as $code) {
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
    "status": "success",
    "code": "2204.21",
    "description": "Brief description of why this code fits",
    "confidence": 90,
    "explanation": "Detailed explanation of the classification"
}

STATUS VALUES:
- "success" = Found a matching code
- "insufficient" = Item doesn't match any provided codes
- "ambiguous" = Multiple codes could apply equally
- "not_found" = Cannot determine classification
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
                                
                                // Build alternatives from priority-sorted codes (title-matched chapters first)
                                $alternatives = $this->buildAlternativesFromCandidates(
                                    $sortedCodes,
                                    $matchedCode->code,
                                    [],
                                    5
                                );
                                
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
                                    'alternatives' => $alternatives,
                                    'customs_code_id' => $matchedCode->id,
                                    'restricted' => false,
                                    'restricted_items' => [],
                                    'exemptions_available' => $matchedCode->getApplicableExemptions()->toArray(),
                                    'duty_calculation' => $matchedCode->calculateTotalDuty(100, 1),
                                ];
                            } else {
                                // AI suggested a code not in our chapters - use first available code and provide alternatives
                                $classificationPath[] = "AI suggested {$aiCode} not found in available codes, using best available";
                                
                                // Use the first code from the chapters as primary
                                $firstCode = $codesInChapters->first();
                                if ($firstCode) {
                                    $alternatives = $this->buildAlternativesFromCandidates(
                                        $codesInChapters,
                                        $firstCode->code,
                                        [],
                                        5
                                    );
                                    
                                    return [
                                        'success' => true,
                                        'item' => $itemDescription,
                                        'code' => $firstCode->code,
                                        'description' => $result['description'] ?? $firstCode->description,
                                        'duty_rate' => $firstCode->duty_rate,
                                        'unit_of_measurement' => $firstCode->formatted_unit,
                                        'special_rate' => $firstCode->special_rate,
                                        'confidence' => max(($result['confidence'] ?? 70) - 20, 50), // Lower confidence
                                        'explanation' => ($result['explanation'] ?? '') . " Note: AI suggested {$aiCode} but this code was not found in the matched chapters. Using closest available code.",
                                        'chapter' => $firstCode->tariffChapter?->formatted_identifier ?? "Chapter " . substr($firstCode->code, 0, 2),
                                        'classification_path' => $classificationPath,
                                        'is_chapter_level' => false,
                                        'alternatives' => $alternatives,
                                        'customs_code_id' => $firstCode->id,
                                        'restricted' => false,
                                        'restricted_items' => [],
                                        'exemptions_available' => $firstCode->getApplicableExemptions()->toArray(),
                                        'duty_calculation' => $firstCode->calculateTotalDuty(100, 1),
                                    ];
                                }
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
            $precedentText .= "These are real prior approvals. Items marked [LEGACY] are from official historical clearances and should be given strong weight as authoritative precedent.\n\n";
            foreach ($precedents as $p) {
                $hs = $p['hs_code'] ?? '';
                $desc = $p['description'] ?? '';
                $legacyTag = !empty($p['is_legacy']) ? ' [LEGACY]' : '';
                $precedentText .= "- {$hs}: {$desc}{$legacyTag}\n";
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
Return a JSON object with a status field:
{
    "status": "success",
    "code": "8417.10",
    "description": "Brief description of why this code fits",
    "duty_rate": 15,
    "confidence": 85,
    "explanation": "Detailed explanation citing any relevant notes",
    "alternatives": ["8417.20", "8418.10"]
}

STATUS VALUES:
- "success" = Found a matching code from the list
- "insufficient" = The item doesn't match any of the provided codes (e.g., looking for electronics but only food codes provided)
- "ambiguous" = Multiple codes could apply equally well, need more item details
- "not_found" = Cannot determine classification from provided information

If status is NOT "success", still provide your best explanation of why and what type of code would be appropriate.

Only return the JSON object, no other text.
PROMPT;
    }

    /**
     * Pull tenant-approved HS code precedents from imported declarations.
     * Prioritizes legacy clearances (external historical data) as the authoritative source.
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

        // Query declaration items with their form's source_type to prioritize legacy clearances
        $candidates = \App\Models\DeclarationFormItem::query()
            ->join('declaration_forms', 'declaration_form_items.declaration_form_id', '=', 'declaration_forms.id')
            ->where('declaration_form_items.country_id', $countryId)
            ->whereNotNull('declaration_form_items.hs_code')
            ->select([
                'declaration_form_items.description',
                'declaration_form_items.hs_code',
                'declaration_forms.source_type',
            ])
            ->orderByRaw("CASE WHEN declaration_forms.source_type = 'legacy' THEN 0 ELSE 1 END")
            ->orderBy('declaration_form_items.created_at', 'desc')
            ->limit(300)
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
                // Give legacy clearance items a significant score boost (they are authoritative)
                $isLegacy = $c->source_type === 'legacy';
                $baseScore = $hits;
                $finalScore = $isLegacy ? ($baseScore * 2) + 10 : $baseScore;

                $scored[] = [
                    'score' => $finalScore,
                    'hs_code' => (string) $c->hs_code,
                    'description' => (string) $c->description,
                    'is_legacy' => $isLegacy,
                ];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, 8);

        return array_map(fn ($r) => [
            'hs_code' => $r['hs_code'],
            'description' => $r['description'],
            'is_legacy' => $r['is_legacy'] ?? false,
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
            'status' => $result['status'] ?? 'success',  // Default to success for backward compatibility
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

    /**
     * Classify using Qdrant vector search for candidates + Claude for reasoning
     * This hybrid approach combines semantic search with AI reasoning
     */
    protected function classifyWithVectorAndClaude(string $itemDescription, ?int $countryId = null, ?int $organizationId = null): array
    {
        $classificationPath = ['Using hybrid mode: Qdrant candidates + Claude reasoning'];
        
        try {
            // Step 0: Check for custom classification rules first
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
                            'confidence' => 95,
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
                        ];
                    }
                }
                
                // Rule has instruction but no target code - include in Claude context
                $classificationPath[] = "Rule instruction will be included in AI context";
            }
            
            // Step 1: Check prohibited/restricted
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

            // Step 2: Use Qdrant to find semantically similar codes (candidates)
            $vectorResult = $this->vectorClassifier->searchSimilarCodes($itemDescription, $countryId, 15);
            
            if (!$vectorResult['success'] || empty($vectorResult['results'])) {
                $classificationPath[] = "Vector search returned no results, falling back to database search";
                // Fall back to full classification mode
                return $this->classifyWithFullMode($itemDescription, $countryId, $organizationId, $classificationPath);
            }
            
            $classificationPath[] = "Qdrant found " . count($vectorResult['results']) . " semantically similar codes";

            // Step 3: Use Qdrant to find relevant chapter notes
            $notesResult = $this->vectorClassifier->searchRelevantNotes($itemDescription, $countryId, 10);
            $relevantNotes = $notesResult['results'] ?? [];
            $classificationPath[] = "Qdrant found " . count($relevantNotes) . " relevant chapter notes";

            // Step 4: Search for exclusion rules that might apply
            $exclusionsResult = $this->vectorClassifier->searchExclusionRules($itemDescription, $countryId, 5);
            $relevantExclusions = $exclusionsResult['results'] ?? [];
            
            // Step 5: Build context for Claude from Qdrant results
            $context = $this->buildVectorContext(
                $vectorResult['results'],
                $relevantNotes,
                $relevantExclusions,
                $ruleResult
            );

            // Step 6: Get tenant precedents (prior approved classifications)
            $precedents = $this->findApprovedReferencePrecedents($itemDescription, $countryId);
            if (!empty($precedents)) {
                $classificationPath[] = "Found " . count($precedents) . " tenant reference precedents";
            }

            // Step 7: Call Claude with the Qdrant-sourced context
            $aiResult = $this->classifyWithClaudeHybrid($itemDescription, $context, $classificationPath, $precedents);
            
            if (empty($aiResult['code'])) {
                // Fall back to best vector match if Claude fails
                $classificationPath[] = "Claude could not determine classification, using best vector match";
                $bestMatch = $vectorResult['results'][0];
                $aiResult = [
                    'code' => $bestMatch['code'],
                    'description' => $bestMatch['description'],
                    'confidence' => max(50, (int)$bestMatch['score'] - 10),
                    'explanation' => 'Classification based on semantic similarity (AI reasoning unavailable)',
                ];
            }

            $classificationPath[] = "Claude classified as: {$aiResult['code']} (confidence: {$aiResult['confidence']}%)";

            // Step 8: Find the matched code in database
            $matchedCode = CustomsCode::where('code', $aiResult['code'])
                ->when($countryId, fn($q) => $q->where('country_id', $countryId))
                ->first();
            
            // If no exact match, find the most specific code that starts with AI's suggestion
            if (!$matchedCode && !empty($aiResult['code'])) {
                $aiCode = $aiResult['code'];
                $normalizedAiCode = str_replace('.', '', $aiCode);
                
                $matchingCodes = CustomsCode::where(function ($q) use ($aiCode, $normalizedAiCode) {
                        $q->where('code', 'like', $aiCode . '%')
                          ->orWhere('code', 'like', $normalizedAiCode . '%');
                    })
                    ->when($countryId, fn($q) => $q->where('country_id', $countryId))
                    ->get();
                
                if ($matchingCodes->isNotEmpty()) {
                    $matchedCode = $matchingCodes->sortByDesc(function ($code) {
                        return strlen(str_replace('.', '', $code->code));
                    })->first();
                    
                    $classificationPath[] = "AI suggested {$aiCode}, found more specific code: {$matchedCode->code}";
                }
            }

            // Step 9: Get exemptions
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

            // Step 10: Calculate duty
            $dutyCalculation = null;
            if ($matchedCode) {
                $dutyCalculation = $matchedCode->calculateTotalDuty(100, 1);
            }

            // Build alternatives from vector results (excluding the selected code)
            $alternatives = [];
            foreach ($vectorResult['results'] as $result) {
                if ($result['code'] !== $aiResult['code'] && count($alternatives) < 5) {
                    $alternatives[] = [
                        'code' => $result['code'],
                        'description' => $result['description'],
                        'score' => $result['score'],
                    ];
                }
            }

            return [
                'success' => true,
                'item' => $itemDescription,
                'code' => $aiResult['code'],
                'description' => $aiResult['description'] ?? $matchedCode?->description,
                'duty_rate' => $matchedCode?->duty_rate ?? $aiResult['duty_rate'] ?? null,
                'unit_of_measurement' => $matchedCode?->formatted_unit,
                'special_rate' => $matchedCode?->special_rate,
                'confidence' => $aiResult['confidence'],
                'explanation' => $aiResult['explanation'] ?? 'Classified using hybrid Qdrant + Claude reasoning',
                'alternatives' => array_merge($aiResult['alternatives'] ?? [], $alternatives),
                'classification_path' => $classificationPath,
                'customs_code_id' => $matchedCode?->id,
                'chapter' => $matchedCode?->tariffChapter?->formatted_identifier,
                'restricted' => $prohibitedCheck['is_restricted'],
                'restricted_items' => $prohibitedCheck['restricted_matches'] ?? [],
                'exemptions_available' => $exemptions,
                'duty_calculation' => $dutyCalculation,
                'source' => 'hybrid',
                'vector_candidates' => array_slice($vectorResult['results'], 0, 5),
            ];

        } catch (\Exception $e) {
            Log::error('Hybrid classification failed', [
                'item' => $itemDescription,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Hybrid classification failed: ' . $e->getMessage(),
                'classification_path' => $classificationPath,
            ];
        }
    }

    /**
     * Build context for Claude from Qdrant search results
     */
    protected function buildVectorContext(array $codes, array $notes, array $exclusions, ?array $ruleResult = null): array
    {
        $context = [
            'codes' => [],
            'notes' => [],
            'exclusions' => [],
            'rule_instruction' => null,
        ];

        // Format codes from vector search
        foreach ($codes as $code) {
            $context['codes'][] = [
                'code' => $code['code'],
                'description' => $code['description'],
                'duty_rate' => $code['duty_rate'],
                'chapter_number' => $code['chapter_number'],
                'chapter_title' => $code['chapter_title'],
                'similarity_score' => $code['score'],
            ];
        }

        // Format chapter notes
        foreach ($notes as $note) {
            $context['notes'][] = [
                'reference' => "Chapter {$note['chapter_number']} - {$note['note_type']}",
                'text' => $note['note_text'],
                'relevance_score' => $note['score'],
            ];
        }

        // Format exclusion rules
        foreach ($exclusions as $exclusion) {
            $context['exclusions'][] = [
                'from_chapter' => $exclusion['source_chapter'],
                'to_chapter' => $exclusion['target_chapter'],
                'pattern' => $exclusion['description_pattern'],
                'reference' => $exclusion['note_reference'],
            ];
        }

        // Include rule instruction if present
        if ($ruleResult && !empty($ruleResult['instruction'])) {
            $context['rule_instruction'] = $ruleResult['instruction'];
        }

        return $context;
    }

    /**
     * Classify with Claude using hybrid context (Qdrant-sourced candidates)
     */
    protected function classifyWithClaudeHybrid(string $itemDescription, array $context, array &$classificationPath, array $precedents = []): array
    {
        $prompt = $this->buildHybridClassificationPrompt($itemDescription, $context, $precedents);
        $estimatedTokens = $this->chunker->estimateTokens($prompt);
        
        $classificationPath[] = "Hybrid AI prompt: {$estimatedTokens} estimated tokens";

        // If too large, reduce codes
        if ($estimatedTokens > 90000) {
            $context['codes'] = array_slice($context['codes'], 0, 10);
            $context['notes'] = array_slice($context['notes'], 0, 5);
            $prompt = $this->buildHybridClassificationPrompt($itemDescription, $context, []);
            $classificationPath[] = "Reduced context due to token limit";
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
     * Build the hybrid classification prompt with Qdrant-sourced context
     */
    protected function buildHybridClassificationPrompt(string $itemDescription, array $context, array $precedents = []): string
    {
        // Format chapter notes (sorted by relevance)
        $notesText = '';
        if (!empty($context['notes'])) {
            $notesText = "## RELEVANT CHAPTER NOTES (by semantic relevance)\n";
            $notesText .= "These notes were found by semantic search and may contain important classification rules:\n\n";
            foreach ($context['notes'] as $note) {
                $score = $note['relevance_score'] ?? 0;
                $notesText .= "### {$note['reference']} (relevance: {$score}%)\n{$note['text']}\n\n";
            }
        }

        // Format exclusion rules
        $exclusionText = '';
        if (!empty($context['exclusions'])) {
            $exclusionText = "## EXCLUSION RULES\n";
            $exclusionText .= "These rules redirect certain items from one chapter to another:\n\n";
            foreach ($context['exclusions'] as $ex) {
                $exclusionText .= "- Items matching \"{$ex['pattern']}\" are excluded from Chapter {$ex['from_chapter']} ";
                $exclusionText .= "and should be classified in Chapter {$ex['to_chapter']}";
                if ($ex['reference']) {
                    $exclusionText .= " (ref: {$ex['reference']})";
                }
                $exclusionText .= "\n";
            }
            $exclusionText .= "\n";
        }

        // Format rule instruction if present
        $ruleInstructionText = '';
        if (!empty($context['rule_instruction'])) {
            $ruleInstructionText = "## CLASSIFICATION GUIDANCE\n";
            $ruleInstructionText .= "The following instruction should be considered:\n";
            $ruleInstructionText .= "> {$context['rule_instruction']}\n\n";
        }

        // Format tenant precedents
        $precedentText = '';
        if (!empty($precedents)) {
            $precedentText .= "## PRIOR APPROVED CLASSIFICATIONS\n";
            $precedentText .= "These are real prior approvals for similar items. Items marked [LEGACY] are from official historical clearances:\n\n";
            foreach ($precedents as $p) {
                $hs = $p['hs_code'] ?? '';
                $desc = $p['description'] ?? '';
                $legacyTag = !empty($p['is_legacy']) ? ' [LEGACY]' : '';
                $precedentText .= "- {$hs}: {$desc}{$legacyTag}\n";
            }
            $precedentText .= "\n";
        }

        // Format candidate codes (from semantic search)
        $codesText = "## CANDIDATE TARIFF CODES (by semantic similarity)\n";
        $codesText .= "These codes were found by semantic search based on description similarity:\n\n";
        foreach ($context['codes'] as $code) {
            $rate = $code['duty_rate'] !== null ? " (Duty: {$code['duty_rate']}%)" : "";
            $chapter = $code['chapter_title'] ? " [Chapter: {$code['chapter_title']}]" : "";
            $score = $code['similarity_score'] ?? 0;
            $codesText .= "- **{$code['code']}** (similarity: {$score}%): {$code['description']}{$rate}{$chapter}\n";
        }

        return <<<PROMPT
You are a customs classification expert. Your task is to classify the item below using the Harmonized System (HS) codes.

## IMPORTANT CONTEXT
The candidate codes below were found by **semantic similarity search**, not keyword matching. This means:
- A product description like "Liver Detox 60 caps" might match codes for organ extracts (because "liver" is similar to "glands/organs")
- BUT you must use **reasoning** to determine the item's TRUE nature
- Dietary/nutritional supplements, vitamins, and health products in capsule/tablet form are typically classified under:
  - **21.06** - Food preparations not elsewhere specified (for supplements without medicinal claims)
  - **30.04** - Medicaments (if they make therapeutic claims and contain active pharmaceutical ingredients)

{$ruleInstructionText}
{$notesText}
{$exclusionText}
{$precedentText}
{$codesText}

## ITEM TO CLASSIFY
"{$itemDescription}"

## YOUR TASK
1. **Analyze the item's true nature** - What is this product actually? (e.g., a dietary supplement, medicine, food, chemical, etc.)
2. **Consider the form** - Capsules, tablets, powders suggest supplements or medicines; raw materials suggest different chapters
3. **Apply chapter notes** - Check if any exclusion rules redirect this item
4. **Select the most appropriate code** - Choose based on the item's nature, not just word similarity

## RESPONSE FORMAT
Return a JSON object with a status field:
{
    "status": "success",
    "code": "21.06",
    "description": "Dietary supplement in capsule form for liver health support",
    "duty_rate": 15,
    "confidence": 85,
    "explanation": "This is a dietary supplement (detox product in capsule form) rather than an organ extract. Supplements are classified under 21.06 as food preparations not elsewhere specified.",
    "alternatives": ["30.04", "30.01"]
}

STATUS VALUES:
- "success" = Found a matching code from the list
- "insufficient" = The item doesn't match any of the provided codes (e.g., looking for electronics but only food codes provided)
- "ambiguous" = Multiple codes could apply equally well, need more item details
- "not_found" = Cannot determine classification from provided information

If status is NOT "success", still provide your best explanation of why and what type of code would be appropriate.

Only return the JSON object, no other text.
PROMPT;
    }

    /**
     * Fall back to full classification mode when hybrid mode fails
     */
    protected function classifyWithFullMode(string $itemDescription, ?int $countryId, ?int $organizationId, array $classificationPath = []): array
    {
        // Temporarily disable hybrid mode to use the original full classification
        $originalHybridMode = $this->useHybridMode;
        $originalVectorOnly = $this->useVectorOnly;
        
        $this->useHybridMode = false;
        $this->useVectorOnly = false;
        
        $result = $this->classify($itemDescription, $countryId, $organizationId);
        
        // Restore settings
        $this->useHybridMode = $originalHybridMode;
        $this->useVectorOnly = $originalVectorOnly;
        
        // Merge classification paths
        if (!empty($classificationPath)) {
            $result['classification_path'] = array_merge($classificationPath, $result['classification_path'] ?? []);
        }
        
        return $result;
    }
}
