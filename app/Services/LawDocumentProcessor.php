<?php

namespace App\Services;

use App\Models\LawDocument;
use App\Models\CustomsCode;
use App\Models\CustomsCodeHistory;
use App\Models\TariffSection;
use App\Models\TariffSectionNote;
use App\Models\TariffChapter;
use App\Models\TariffChapterNote;
use App\Models\ExemptionCategory;
use App\Models\ExemptionCondition;
use App\Models\ProhibitedGood;
use App\Models\RestrictedGood;
use App\Models\AdditionalLevy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory as WordFactory;

class LawDocumentProcessor
{
    protected string $apiKey;
    protected string $model;
    protected int $maxTokens;
    protected DocumentChunker $chunker;
    protected ExclusionRuleParser $exclusionParser;
    protected ?UnstructuredApiClient $unstructuredApi;

    /**
     * Processing statistics
     */
    protected array $stats = [
        'sections_created' => 0,
        'chapters_created' => 0,
        'notes_created' => 0,
        'codes_created' => 0,
        'codes_updated' => 0,
        'exemptions_created' => 0,
        'prohibited_created' => 0,
        'restricted_created' => 0,
        'levies_created' => 0,
        'exclusion_rules_created' => 0,
    ];

    public function __construct(
        DocumentChunker $chunker, 
        ExclusionRuleParser $exclusionParser,
        ?UnstructuredApiClient $unstructuredApi = null
    ) {
        $this->apiKey = config('services.claude.api_key');
        $this->model = config('services.claude.model');
        $this->maxTokens = config('services.claude.max_tokens');
        $this->chunker = $chunker;
        $this->exclusionParser = $exclusionParser;
        $this->unstructuredApi = $unstructuredApi ?? app(UnstructuredApiClient::class);
    }

    /**
     * Process a law document with multi-pass extraction
     */
    public function process(LawDocument $document): array
    {
        // Ensure long execution time for CLI/queue processing
        set_time_limit(1800); // 30 minutes
        ini_set('memory_limit', '1G');
        
        $this->resetStats();
        
        try {
            $document->markAsProcessing();

            // Extract text from document
            $text = $this->extractText($document);

            if (empty($text)) {
                throw new \Exception('Could not extract text from document');
            }

            $countryId = $document->country_id;

            // Multi-pass extraction
            Log::info('Starting multi-pass document processing', ['document_id' => $document->id]);

            // Pass 1: Extract sections and chapters structure
            $this->extractStructure($text, $countryId);

            // Pass 2: Extract all notes (section, chapter, subheading)
            $this->extractNotes($text, $countryId);

            // Pass 3: Parse exclusion rules from notes
            $exclusionResults = $this->exclusionParser->parseAllForCountry($countryId);
            $this->stats['exclusion_rules_created'] = $exclusionResults['created'];

            // Pass 4: Extract ALL tariff codes with full details
            $this->extractTariffCodes($text, $countryId, $document);

            // Pass 5: Extract exemptions (Schedule 5)
            $this->extractExemptions($text, $countryId);

            // Pass 6: Extract prohibited/restricted goods
            $this->extractProhibitedAndRestricted($text, $countryId);

            // Pass 7: Extract additional levies
            $this->extractLevies($text, $countryId);

            // Pass 8: Compute cached classification fields
            $this->computeClassificationAids($countryId);

            $document->markAsCompleted();

            Log::info('Multi-pass processing complete', $this->stats);

            return [
                'success' => true,
                'stats' => $this->stats,
            ];

        } catch (\Exception $e) {
            Log::error('Law document processing failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $document->markAsFailed($e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'stats' => $this->stats,
            ];
        }
    }

    /**
     * Reset processing statistics
     */
    protected function resetStats(): void
    {
        $this->stats = [
            'sections_created' => 0,
            'chapters_created' => 0,
            'notes_created' => 0,
            'codes_created' => 0,
            'codes_updated' => 0,
            'exemptions_created' => 0,
            'prohibited_created' => 0,
            'restricted_created' => 0,
            'levies_created' => 0,
            'exclusion_rules_created' => 0,
        ];
    }

    /**
     * Pass 1: Extract sections and chapters structure
     */
    protected function extractStructure(string $text, int $countryId): void
    {
        Log::info('Pass 1: Extracting document structure');

        $prompt = $this->buildStructurePrompt($text);
        $response = $this->callClaudeAPI($prompt, 120); // Give more time for comprehensive response
        
        Log::info('Structure API response (first 2000 chars)', ['response' => substr($response, 0, 2000)]);
        
        $structure = $this->parseJsonResponse($response);
        
        Log::info('Parsed structure', [
            'has_chapters' => isset($structure['chapters']),
            'chapter_count' => count($structure['chapters'] ?? []),
            'has_sections' => isset($structure['sections']),
        ]);

        if (empty($structure)) {
            Log::warning('No structure extracted from document');
            return;
        }

        // Extract chapters with proper titles
        foreach ($structure['chapters'] ?? [] as $chapterData) {
            $chapterNum = str_pad($chapterData['number'] ?? '', 2, '0', STR_PAD_LEFT);
            $title = $chapterData['title'] ?? '';
            
            // Skip if we don't have valid data
            if (empty($chapterNum) || $chapterNum === '00') {
                continue;
            }
            
            // Don't use generic "Chapter XX" titles
            if (empty($title) || preg_match('/^Chapter\s*\d+$/i', $title)) {
                $title = "Chapter {$chapterNum}"; // Fallback, but log it
                Log::warning("Generic chapter title for chapter {$chapterNum}");
            }
            
            $existingChapter = TariffChapter::where('chapter_number', $chapterNum)
                ->where('country_id', $countryId)
                ->first();
            
            $chapter = TariffChapter::updateOrCreate(
                [
                    'chapter_number' => $chapterNum,
                    'country_id' => $countryId,
                ],
                [
                    'title' => $title,
                    'description' => $chapterData['description'] ?? null,
                ]
            );

            if (!$existingChapter) {
                $this->stats['chapters_created']++;
                Log::info("Chapter created", ['number' => $chapterNum, 'title' => $title]);
            }
        }

        // Also handle sections if provided (legacy format)
        foreach ($structure['sections'] ?? [] as $sectionData) {
            $section = TariffSection::updateOrCreate(
                [
                    'section_number' => $sectionData['number'],
                    'country_id' => $countryId,
                ],
                [
                    'title' => $sectionData['title'] ?? '',
                    'description' => $sectionData['description'] ?? null,
                ]
            );

            if ($section->wasRecentlyCreated) {
                $this->stats['sections_created']++;
            }
        }
    }

    /**
     * Pass 2: Extract notes
     */
    protected function extractNotes(string $text, int $countryId): void
    {
        Log::info('Pass 2: Extracting notes');

        // Get all chapters for this country
        $chapters = TariffChapter::where('country_id', $countryId)->get()->keyBy('chapter_number');

        // Chunk the text and extract notes from each chunk
        $chunks = $this->chunker->chunkText($text);
        
        foreach ($chunks as $index => $chunk) {
            Log::info("Extracting notes from chunk " . ($index + 1) . " of " . count($chunks));
            
            $prompt = $this->buildNotesPrompt($chunk);
            $response = $this->callClaudeAPI($prompt);
            $notes = $this->parseJsonResponse($response);

            foreach ($notes['chapter_notes'] ?? [] as $noteData) {
                $chapterNum = str_pad($noteData['chapter'] ?? '', 2, '0', STR_PAD_LEFT);
                $chapter = $chapters[$chapterNum] ?? null;

                if (!$chapter) {
                    // Create chapter if it doesn't exist
                    $chapter = TariffChapter::create([
                        'chapter_number' => $chapterNum,
                        'title' => "Chapter {$chapterNum}",
                        'country_id' => $countryId,
                    ]);
                    $chapters[$chapterNum] = $chapter;
                    $this->stats['chapters_created']++;
                }

                TariffChapterNote::updateOrCreate(
                    [
                        'tariff_chapter_id' => $chapter->id,
                        'note_number' => $noteData['note_number'] ?? null,
                        'note_type' => $noteData['type'] ?? 'general',
                    ],
                    [
                        'note_text' => $noteData['text'] ?? '',
                    ]
                );
                $this->stats['notes_created']++;
            }
        }
    }

    /**
     * Pass 4: Extract tariff codes with full details
     */
    protected function extractTariffCodes(string $text, int $countryId, LawDocument $document): void
    {
        Log::info('Pass 4: Extracting tariff codes');

        $chapters = TariffChapter::where('country_id', $countryId)->get()->keyBy('chapter_number');
        $chunks = $this->chunker->chunkText($text);
        $seenCodes = [];

        // FULL MODE: Process all chunks for complete extraction
        $testMode = false;
        $maxChunks = $testMode ? 1 : count($chunks);

        foreach (array_slice($chunks, 0, $maxChunks) as $index => $chunk) {
            Log::info("Extracting codes from chunk " . ($index + 1) . " of " . $maxChunks . " (full mode)");

            $prompt = $this->buildCodesPrompt($chunk);
            $response = $this->callClaudeAPI($prompt, 300); // 5 min timeout for larger batches
            $codes = $this->parseJsonResponse($response);

            foreach ($codes as $codeData) {
                if (empty($codeData['code']) || isset($seenCodes[$codeData['code']])) {
                    continue;
                }
                $seenCodes[$codeData['code']] = true;

                // Determine chapter and code level
                $chapterNum = $this->extractChapterNumber($codeData['code']);
                $chapter = $chapters[$chapterNum] ?? null;
                $codeLevel = $this->determineCodeLevel($codeData['code']);

                // Extract keywords from description
                $keywords = $this->extractKeywords($codeData['description'] ?? '');

                // Find or create the code
                $existing = CustomsCode::where('code', $codeData['code'])
                    ->where('country_id', $countryId)
                    ->first();

                $data = [
                    'description' => $codeData['description'] ?? '',
                    'duty_rate' => $this->cleanNumericRate($codeData['duty_rate'] ?? 0),
                    'tariff_chapter_id' => $chapter?->id,
                    'parent_code' => $codeData['parent_code'] ?? $this->determineParentCode($codeData['code']),
                    'code_level' => $codeLevel,
                    'unit_of_measurement' => $codeData['unit'] ?? null,
                    'unit_secondary' => $codeData['unit_secondary'] ?? null,
                    'special_rate' => $this->cleanNumericRate($codeData['special_rate'] ?? null),
                    'notes' => $codeData['notes'] ?? null,
                    'classification_keywords' => $keywords,
                    'inclusion_hints' => $codeData['inclusion_hints'] ?? null,
                ];

                if ($existing) {
                    $this->trackChanges($existing, $data, $document);
                    $existing->update($data);
                    $this->stats['codes_updated']++;
                } else {
                    $newCode = CustomsCode::create(array_merge($data, [
                        'code' => $codeData['code'],
                        'country_id' => $countryId,
                    ]));

                    CustomsCodeHistory::logChange(
                        $newCode->id,
                        'created',
                        null,
                        "Code created from law document",
                        $document->id,
                        auth()->id()
                    );

                    $this->stats['codes_created']++;
                }
            }
        }
    }

    /**
     * Pass 5: Extract exemptions
     */
    protected function extractExemptions(string $text, int $countryId): void
    {
        Log::info('Pass 5: Extracting exemptions');

        $prompt = $this->buildExemptionsPrompt($text);
        $response = $this->callClaudeAPI($prompt, 180);
        $exemptions = $this->parseJsonResponse($response);

        foreach ($exemptions as $exemptionData) {
            if (empty($exemptionData['name'])) {
                continue;
            }

            $category = ExemptionCategory::updateOrCreate(
                [
                    'name' => $exemptionData['name'],
                    'country_id' => $countryId,
                ],
                [
                    'description' => $exemptionData['description'] ?? null,
                    'legal_reference' => $exemptionData['legal_reference'] ?? null,
                    'applies_to_patterns' => $exemptionData['applies_to_patterns'] ?? null,
                    'is_active' => true,
                ]
            );

            if ($category->wasRecentlyCreated) {
                $this->stats['exemptions_created']++;
            }

            // Create conditions
            foreach ($exemptionData['conditions'] ?? [] as $condData) {
                ExemptionCondition::updateOrCreate(
                    [
                        'exemption_category_id' => $category->id,
                        'condition_type' => $condData['type'] ?? 'other',
                        'description' => $condData['description'] ?? '',
                    ],
                    [
                        'requirement_text' => $condData['requirement'] ?? null,
                        'is_mandatory' => $condData['mandatory'] ?? true,
                    ]
                );
            }
        }
    }

    /**
     * Pass 6: Extract prohibited and restricted goods
     */
    protected function extractProhibitedAndRestricted(string $text, int $countryId): void
    {
        Log::info('Pass 6: Extracting prohibited and restricted goods');

        $prompt = $this->buildProhibitedRestrictedPrompt($text);
        $response = $this->callClaudeAPI($prompt);
        $result = $this->parseJsonResponse($response);

        // Process prohibited goods
        foreach ($result['prohibited'] ?? [] as $item) {
            if (empty($item['name'])) continue;

            $good = ProhibitedGood::updateOrCreate(
                [
                    'name' => $item['name'],
                    'country_id' => $countryId,
                ],
                [
                    'description' => $item['description'] ?? null,
                    'legal_reference' => $item['legal_reference'] ?? null,
                    'detection_keywords' => $item['keywords'] ?? $this->extractKeywords($item['name'] . ' ' . ($item['description'] ?? '')),
                ]
            );

            if ($good->wasRecentlyCreated) {
                $this->stats['prohibited_created']++;
            }
        }

        // Process restricted goods
        foreach ($result['restricted'] ?? [] as $item) {
            if (empty($item['name'])) continue;

            $good = RestrictedGood::updateOrCreate(
                [
                    'name' => $item['name'],
                    'country_id' => $countryId,
                ],
                [
                    'description' => $item['description'] ?? null,
                    'restriction_type' => $item['restriction_type'] ?? 'permit',
                    'permit_authority' => $item['permit_authority'] ?? null,
                    'requirements' => $item['requirements'] ?? null,
                    'detection_keywords' => $item['keywords'] ?? $this->extractKeywords($item['name'] . ' ' . ($item['description'] ?? '')),
                ]
            );

            if ($good->wasRecentlyCreated) {
                $this->stats['restricted_created']++;
            }
        }
    }

    /**
     * Pass 7: Extract additional levies
     */
    protected function extractLevies(string $text, int $countryId): void
    {
        Log::info('Pass 7: Extracting additional levies');

        $prompt = $this->buildLeviesPrompt($text);
        $response = $this->callClaudeAPI($prompt);
        $levies = $this->parseJsonResponse($response);

        foreach ($levies as $levyData) {
            if (empty($levyData['name'])) continue;

            $chapterId = null;
            if (!empty($levyData['chapter'])) {
                $chapter = TariffChapter::where('chapter_number', str_pad($levyData['chapter'], 2, '0', STR_PAD_LEFT))
                    ->where('country_id', $countryId)
                    ->first();
                $chapterId = $chapter?->id;
            }

            $levy = AdditionalLevy::updateOrCreate(
                [
                    'levy_name' => $levyData['name'],
                    'country_id' => $countryId,
                    'tariff_chapter_id' => $chapterId,
                ],
                [
                    'rate' => $levyData['rate'] ?? 0,
                    'rate_type' => $levyData['rate_type'] ?? 'per_unit',
                    'unit' => $levyData['unit'] ?? null,
                    'legal_reference' => $levyData['legal_reference'] ?? null,
                    'exempt_organizations' => $levyData['exempt_organizations'] ?? null,
                    'is_active' => true,
                ]
            );

            if ($levy->wasRecentlyCreated) {
                $this->stats['levies_created']++;
            }
        }
    }

    /**
     * Pass 8: Compute cached classification aids
     */
    protected function computeClassificationAids(int $countryId): void
    {
        Log::info('Pass 8: Computing classification aids');

        // Get all codes for this country
        $codes = CustomsCode::where('country_id', $countryId)->get();

        // Get all exemptions for pattern matching
        $exemptions = ExemptionCategory::where('country_id', $countryId)
            ->where('is_active', true)
            ->get();

        foreach ($codes as $code) {
            $updates = [];

            // Compute applicable note IDs
            if ($code->tariff_chapter_id) {
                $noteIds = TariffChapterNote::where('tariff_chapter_id', $code->tariff_chapter_id)
                    ->pluck('id')
                    ->toArray();
                
                // Add section notes if chapter has a section
                $chapter = TariffChapter::find($code->tariff_chapter_id);
                if ($chapter && $chapter->tariff_section_id) {
                    $sectionNoteIds = TariffSectionNote::where('tariff_section_id', $chapter->tariff_section_id)
                        ->pluck('id')
                        ->toArray();
                    $noteIds = array_merge($sectionNoteIds, $noteIds);
                }
                
                $updates['applicable_note_ids'] = $noteIds;
            }

            // Compute applicable exemption IDs
            $applicableExemptions = [];
            foreach ($exemptions as $exemption) {
                if ($exemption->matchesCode($code->code)) {
                    $applicableExemptions[] = $exemption->id;
                }
            }
            if (!empty($applicableExemptions)) {
                $updates['applicable_exemption_ids'] = $applicableExemptions;
            }

            // Find similar codes (same heading, different subheading)
            $baseCode = substr($code->code, 0, 4);
            $similarIds = CustomsCode::where('country_id', $countryId)
                ->where('code', 'like', $baseCode . '%')
                ->where('id', '!=', $code->id)
                ->limit(5)
                ->pluck('id')
                ->toArray();
            if (!empty($similarIds)) {
                $updates['similar_code_ids'] = $similarIds;
            }

            if (!empty($updates)) {
                $code->update($updates);
            }
        }
    }

    // ==================== PROMPT BUILDERS ====================

    protected function buildStructurePrompt(string $text): string
    {
        // Use AI with comprehensive sampling
        $textLen = strlen($text);
        $sampleSize = 8000;
        $samples = [];
        
        // Take 15 samples evenly distributed
        for ($i = 0; $i < 15; $i++) {
            $offset = (int)($textLen * ($i / 15));
            $samples[] = substr($text, $offset, $sampleSize);
        }
        $combinedSample = implode("\n\n--- SECTION BREAK ---\n\n", $samples);
        
        return <<<PROMPT
Extract ALL tariff chapter information from this customs/tariff document.

This document contains a TARIFF SCHEDULE with chapters numbered 01 through 97. 
Look for patterns like:
- "Chapter 1" or "CHAPTER 01" or "Ch. 01"
- "Notes." followed by chapter content
- Headers with chapter numbers and titles
- Tariff codes starting with chapter numbers (e.g., 01.01, 28.07, 84.18)

IMPORTANT: The document may have section breaks where I've extracted multiple parts. 
Extract chapters from ALL sections.

For each chapter found, extract:
- number: The 2-digit chapter number (01-97)  
- title: The FULL descriptive title (e.g., "Live animals", NOT "Chapter 01")

Return JSON:
{
    "chapters": [
        {"number": "01", "title": "Live animals"},
        {"number": "02", "title": "Meat and edible meat offal"},
        {"number": "03", "title": "Fish and crustaceans, molluscs and other aquatic invertebrates"}
    ]
}

If you see tariff codes like "01.01", "28.07", "84.18" - these indicate chapters 01, 28, 84 respectively.
If you find chapter notes for chapter X, that chapter exists.

DOCUMENT SAMPLES:
{$combinedSample}
PROMPT;
    }

    protected function buildNotesPrompt(string $text): string
    {
        return <<<PROMPT
Extract all TARIFF CHAPTER NOTES from this customs tariff schedule text.

These are notes that appear at the beginning of tariff chapters (numbered 01-97) that define:
- What products are EXCLUDED from the chapter
- Definitions for the purposes of the chapter
- Classification rules

Look for patterns like:
- "Notes." or "Chapter Notes:" followed by numbered items (1., 2., etc.)
- "This Chapter does not cover..." (exclusion notes)
- "For the purposes of this Chapter..." (definition notes)
- "Subheading Notes" 

IMPORTANT: 
- Extract notes from tariff chapters (01, 02, 28, 84, etc.), NOT from legal document parts
- Chapter numbers are 2 digits (01 through 97)

Return JSON:
{
    "chapter_notes": [
        {
            "chapter": "84",
            "note_number": 1,
            "type": "exclusion",
            "text": "This Chapter does not cover articles of heading 82.07"
        },
        {
            "chapter": "28",
            "note_number": 1,
            "type": "definition",
            "text": "For the purposes of this Chapter, 'chemical elements' means..."
        }
    ]
}

Types: "exclusion" (what the chapter does NOT cover), "definition" (what terms mean), "general" (other rules), "subheading_note" (notes for specific subheadings)

Only return valid JSON.

DOCUMENT:
{$text}
PROMPT;
    }

    protected function buildCodesPrompt(string $text): string
    {
        // OPTIMIZED: Extract tariff codes with balanced prompt
        return <<<PROMPT
Extract tariff codes from this customs schedule text.
Return JSON array: [{"code":"01.01","description":"Live horses","duty_rate":0,"unit":"No"}]
Use 0 for Free duty rates. Extract all codes you find in this section.

Text:
[
    {
        "code": "01.01",
        "description": "Live horses, asses, mules and hinnies",
        "duty_rate": 0,
        "unit": "kg and No",
        "unit_secondary": null,
        "special_rate": null,
        "parent_code": null,
        "notes": null,
        "inclusion_hints": "Live animals only"
    }
]

Select 10 codes covering: live animals (Ch 01-05), chemicals (Ch 28-29), machinery (Ch 84-85), vehicles (Ch 87), and any others you find.

DOCUMENT:
{$text}
PROMPT;
    }

    protected function buildExemptionsPrompt(string $text): string
    {
        // Look for Schedule 5 or exemption sections
        $searchTerms = ['Schedule 5', 'exempt from duty', 'duty free', 'exemption', 'Exempted Goods'];
        $relevantText = '';
        
        foreach ($searchTerms as $term) {
            $pos = stripos($text, $term);
            if ($pos !== false) {
                $relevantText .= substr($text, max(0, $pos - 100), 10000) . "\n\n";
            }
        }
        
        if (empty($relevantText)) {
            $relevantText = substr($text, -30000); // Use end of document
        }

        return <<<PROMPT
Extract all exemption categories from this customs document (typically Schedule 5 or similar).

For each exemption, extract:
- name: Category name (e.g., "Church vehicles", "Computer hardware")
- description: What is exempt
- legal_reference: Legal reference (e.g., "Schedule 5, Para 19")
- applies_to_patterns: Array of tariff code patterns it applies to (e.g., ["8471*"])
- conditions: Array of conditions for the exemption

Return JSON array:
[
    {
        "name": "Computer hardware",
        "description": "Computer hardware except mainframe computers",
        "legal_reference": "Schedule 5, Para 19",
        "applies_to_patterns": ["8471*", "8473*"],
        "conditions": [
            {
                "type": "purpose",
                "description": "Must be for personal/business use",
                "requirement": "Not for resale",
                "mandatory": true
            }
        ]
    }
]

Only return valid JSON array.

DOCUMENT:
{$relevantText}
PROMPT;
    }

    protected function buildProhibitedRestrictedPrompt(string $text): string
    {
        // Search for prohibited/restricted sections
        $relevantText = '';
        $terms = ['PROHIBITED GOODS', 'RESTRICTED GOODS', 'Part I', 'Part II'];
        
        foreach ($terms as $term) {
            $pos = stripos($text, $term);
            if ($pos !== false) {
                $relevantText .= substr($text, $pos, 5000) . "\n\n";
            }
        }

        return <<<PROMPT
Extract all prohibited and restricted goods from this customs document.

PROHIBITED = Cannot import at all
RESTRICTED = Requires permit/license

Return JSON:
{
    "prohibited": [
        {
            "name": "Counterfeit currency",
            "description": "Base or counterfeit coin",
            "legal_reference": "...",
            "keywords": ["counterfeit", "fake", "currency", "coin"]
        }
    ],
    "restricted": [
        {
            "name": "Firearms",
            "description": "Firearms or ammunition",
            "restriction_type": "permit",
            "permit_authority": "Commissioner of Police",
            "requirements": "Must have valid permit",
            "keywords": ["firearms", "guns", "ammunition", "weapons"]
        }
    ]
}

Only return valid JSON.

DOCUMENT:
{$relevantText}
PROMPT;
    }

    protected function buildLeviesPrompt(string $text): string
    {
        // Search for levy mentions
        $relevantText = '';
        $terms = ['levy', 'surcharge', 'additional', 'cents per gallon'];
        
        foreach ($terms as $term) {
            $pos = stripos($text, $term);
            if ($pos !== false) {
                $relevantText .= substr($text, max(0, $pos - 200), 2000) . "\n\n";
            }
        }

        return <<<PROMPT
Extract all additional levies, surcharges, or extra duties from this customs document.

Look for:
- Environmental levies
- Transportation fund levies
- Fuel surcharges
- Any additional charges beyond standard duty

Return JSON array:
[
    {
        "name": "Transportation Network Improvement Fund Levy",
        "chapter": "27",
        "rate": 0.10,
        "rate_type": "per_unit",
        "unit": "gallon",
        "legal_reference": "Section 55(1)",
        "exempt_organizations": ["BVI Electricity Corporation", "Public Works Department"]
    }
]

Only return valid JSON array.

DOCUMENT:
{$relevantText}
PROMPT;
    }

    // ==================== HELPER METHODS ====================

    /**
     * Call Claude API
     */
    protected function callClaudeAPI(string $prompt, int $timeout = 300): string
    {
        $response = Http::withoutVerifying()
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->timeout($timeout)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (!$response->successful()) {
            Log::error('Claude API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Claude API request failed: ' . $response->body());
        }

        $result = $response->json();
        return $result['content'][0]['text'] ?? '';
    }

    /**
     * Parse JSON from API response
     */
    protected function parseJsonResponse(string $content): array
    {
        $content = trim($content);

        // Remove markdown code blocks (```json ... ``` or ``` ... ```)
        $content = preg_replace('/```json?\s*\n?/i', '', $content);
        $content = preg_replace('/\n?```\s*$/', '', $content);
        $content = trim($content);

        // Try to find JSON array first (most common for code extraction)
        if (preg_match('/\[[\s\S]*\]/s', $content, $matches)) {
            $content = $matches[0];
        } elseif (preg_match('/\{[\s\S]*\}/s', $content, $matches)) {
            $content = $matches[0];
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to repair truncated JSON
            $repaired = $this->repairTruncatedJson($content);
            $data = json_decode($repaired, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to parse JSON response', [
                    'error' => json_last_error_msg(),
                    'content' => substr($content, 0, 500),
                ]);
                return [];
            }
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Repair truncated JSON
     */
    protected function repairTruncatedJson(string $json): string
    {
        $json = preg_replace('/,\s*\{[^}]*$/', '', $json);
        $json = preg_replace('/,\s*\[[^\]]*$/', '', $json);

        $openBrackets = substr_count($json, '[');
        $closeBrackets = substr_count($json, ']');
        $openBraces = substr_count($json, '{');
        $closeBraces = substr_count($json, '}');

        $json .= str_repeat('}', max(0, $openBraces - $closeBraces));
        $json .= str_repeat(']', max(0, $openBrackets - $closeBrackets));

        return $json;
    }

    /**
     * Extract chapter number from code
     */
    protected function extractChapterNumber(string $code): string
    {
        if (preg_match('/^(\d{2})/', $code, $matches)) {
            return $matches[1];
        }
        return '00';
    }

    /**
     * Determine code level from format
     */
    protected function determineCodeLevel(string $code): string
    {
        $code = preg_replace('/[^0-9.]/', '', $code);
        
        if (preg_match('/^\d{2}$/', $code)) {
            return CustomsCode::LEVEL_CHAPTER;
        }
        if (preg_match('/^\d{2}\.\d{2}$/', $code)) {
            return CustomsCode::LEVEL_HEADING;
        }
        if (preg_match('/^\d{4}\.\d{2}$/', $code)) {
            return CustomsCode::LEVEL_SUBHEADING;
        }
        return CustomsCode::LEVEL_ITEM;
    }

    /**
     * Clean numeric rate by removing currency symbols and converting to decimal
     */
    protected function cleanNumericRate($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        // Convert to string if not already
        $value = (string) $value;
        
        // Remove currency symbols ($, £, €, ¥, ₹, etc.) and commas
        $cleaned = preg_replace('/[$£€¥₹,]/', '', $value);
        
        // Extract numeric value
        if (preg_match('/-?\d+\.?\d*/', $cleaned, $matches)) {
            return (float) $matches[0];
        }
        
        return null;
    }

    /**
     * Determine parent code
     */
    protected function determineParentCode(string $code): ?string
    {
        $code = preg_replace('/[^0-9.]/', '', $code);

        // 8417.101 -> 8417.10
        if (preg_match('/^(\d{4}\.\d{2})\d+$/', $code, $matches)) {
            return $matches[1];
        }
        // 8417.10 -> 84.17
        if (preg_match('/^(\d{2})(\d{2})\.(\d{2})$/', $code, $matches)) {
            return $matches[1] . '.' . $matches[2];
        }
        // 84.17 -> 84
        if (preg_match('/^(\d{2})\.\d{2}$/', $code, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract keywords from text
     */
    protected function extractKeywords(string $text): array
    {
        $stopWords = ['the', 'of', 'and', 'or', 'a', 'an', 'in', 'to', 'for', 'with', 'as', 'at', 'by', 'on', 'is', 'are', 'other', 'not', 'than'];

        preg_match_all('/\b([a-z]{3,})\b/i', strtolower($text), $matches);
        $words = array_diff($matches[1] ?? [], $stopWords);
        $words = array_unique($words);
        $words = array_slice($words, 0, 10);

        return array_values($words);
    }

    /**
     * Track changes to existing customs code
     */
    protected function trackChanges(CustomsCode $existing, array $newData, LawDocument $document): void
    {
        $fields = ['description', 'duty_rate'];

        foreach ($fields as $field) {
            $oldValue = (string) $existing->$field;
            $newValue = (string) ($newData[$field] ?? '');

            if ($oldValue !== $newValue && !empty($newValue)) {
                CustomsCodeHistory::logChange(
                    $existing->id,
                    $field,
                    $oldValue ?: null,
                    $newValue ?: null,
                    $document->id,
                    auth()->id()
                );
            }
        }
    }

    // ==================== DOCUMENT EXTRACTION ====================

    protected function extractText(LawDocument $document, bool $forceReExtract = false): string
    {
        // Check for cached extracted text (skip Unstructured API call if already extracted)
        if (!$forceReExtract && $document->hasExtractedText()) {
            Log::info('Using cached extracted text', [
                'document_id' => $document->id,
                'extracted_at' => $document->extracted_at,
                'text_length' => strlen($document->extracted_text),
            ]);
            return $document->extracted_text;
        }

        $fullPath = $document->getFullPath();

        if (!file_exists($fullPath)) {
            throw new \Exception('Document file not found');
        }

        Log::info('Extracting text from document (not cached)', ['document_id' => $document->id]);

        $text = match (strtolower($document->file_type)) {
            'pdf' => $this->extractFromPdf($fullPath),
            'docx', 'doc' => $this->extractFromWord($fullPath),
            'txt' => file_get_contents($fullPath),
            'xlsx', 'xls' => $this->extractFromExcel($fullPath),
            default => throw new \Exception("Unsupported file type: {$document->file_type}"),
        };

        // Cache the extracted text for future reprocessing
        if (!empty($text)) {
            $document->storeExtractedText($text);
            Log::info('Cached extracted text for future use', [
                'document_id' => $document->id,
                'text_length' => strlen($text),
            ]);
        }

        return $text;
    }

    protected function extractFromPdf(string $path): string
    {
        set_time_limit(600); // 10 minutes for large PDFs
        
        // Try Unstructured API first (handles OCR, large files, better extraction)
        if ($this->unstructuredApi) {
            try {
                Log::info('Using Unstructured API for PDF extraction', ['file' => basename($path)]);
                
                // Check if API is available
                if (!$this->unstructuredApi->healthCheck()) {
                    Log::warning('Unstructured API health check failed, falling back to local parser');
                } else {
                    // Use 'fast' strategy for optimal performance and to avoid timeouts
                    // Fast strategy: Quick extraction with basic parsing, ideal for large documents
                    $text = $this->unstructuredApi->extractText($path, 'fast');
                    
                    if (!empty(trim($text))) {
                        Log::info('Successfully extracted text via Unstructured API', [
                            'text_length' => strlen($text),
                            'file' => basename($path)
                        ]);
                        return $text;
                    }
                    
                    Log::warning('Unstructured API returned empty text, falling back to local parser');
                }
            } catch (\Throwable $e) {
                Log::warning('Unstructured API extraction failed, falling back to local parser', [
                    'error' => $e->getMessage(),
                    'file' => basename($path)
                ]);
            }
        }
        
        // Fallback to local PdfParser
        Log::info('Using local PdfParser for extraction', ['file' => basename($path)]);
        $parser = new PdfParser();
        $pdf = $parser->parseFile($path);
        return $pdf->getText();
    }

    protected function extractFromWord(string $path): string
    {
        $phpWord = WordFactory::load($path);
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                } elseif (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $childElement) {
                        if (method_exists($childElement, 'getText')) {
                            $text .= $childElement->getText() . "\n";
                        }
                    }
                }
            }
        }

        return $text;
    }

    protected function extractFromExcel(string $path): string
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $text = '';

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $value = $cell->getValue();
                    if ($value !== null) {
                        $rowData[] = $value;
                    }
                }
                
                if (!empty($rowData)) {
                    $text .= implode(' | ', $rowData) . "\n";
                }
            }
        }

        return $text;
    }
}
