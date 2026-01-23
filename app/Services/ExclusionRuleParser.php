<?php

namespace App\Services;

use App\Models\ClassificationExclusion;
use App\Models\TariffChapter;
use App\Models\TariffChapterNote;
use App\Models\TariffSectionNote;
use Illuminate\Support\Facades\Log;

class ExclusionRuleParser
{
    /**
     * Patterns that indicate exclusions in note text
     */
    protected array $exclusionPatterns = [
        // "This Chapter does not cover:"
        '/this\s+(?:chapter|heading|section)\s+does\s+not\s+cover[:\s]+(.+?)(?:\.|;|$)/is',
        // "does not include"
        '/does\s+not\s+include[:\s]+(.+?)(?:\.|;|$)/is',
        // "excluding"
        '/excluding[:\s]+(.+?)(?:\.|;|$)/is',
        // "except"
        '/except(?:\s+for)?[:\s]+(.+?)(?:\.|;|$)/is',
        // "other than"
        '/other\s+than[:\s]+(.+?)(?:\.|;|$)/is',
        // Specific heading references like "(heading No. 69.03)"
        '/\((?:heading|chapter)\s*(?:No\.?)?\s*(\d{2}(?:\.\d{2})?)\)/i',
    ];

    /**
     * Parse exclusion rules from all notes for a country
     */
    public function parseAllForCountry(int $countryId): array
    {
        $results = [
            'parsed' => 0,
            'created' => 0,
            'errors' => [],
        ];

        // Parse chapter notes
        $chapterNotes = TariffChapterNote::whereHas('chapter', function ($q) use ($countryId) {
            $q->where('country_id', $countryId);
        })->with('chapter')->get();

        foreach ($chapterNotes as $note) {
            try {
                $exclusions = $this->parseNoteText($note->note_text, $note->chapter, $countryId);
                foreach ($exclusions as $exclusion) {
                    $exclusion['source_note_reference'] = $note->formatted_reference;
                    $this->createOrUpdateExclusion($exclusion);
                    $results['created']++;
                }
                $results['parsed']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Chapter note {$note->id}: {$e->getMessage()}";
            }
        }

        // Parse section notes
        $sectionNotes = TariffSectionNote::whereHas('section', function ($q) use ($countryId) {
            $q->where('country_id', $countryId);
        })->with('section.chapters')->get();

        foreach ($sectionNotes as $note) {
            try {
                // Section notes apply to all chapters in the section
                foreach ($note->section->chapters as $chapter) {
                    $exclusions = $this->parseNoteText($note->note_text, $chapter, $countryId);
                    foreach ($exclusions as $exclusion) {
                        $exclusion['source_note_reference'] = $note->formatted_reference;
                        $this->createOrUpdateExclusion($exclusion);
                        $results['created']++;
                    }
                }
                $results['parsed']++;
            } catch (\Exception $e) {
                $results['errors'][] = "Section note {$note->id}: {$e->getMessage()}";
            }
        }

        Log::info('Exclusion rule parsing complete', $results);
        return $results;
    }

    /**
     * Parse exclusion rules from a single note text
     */
    public function parseNoteText(string $noteText, TariffChapter $sourceChapter, int $countryId): array
    {
        $exclusions = [];

        foreach ($this->exclusionPatterns as $pattern) {
            if (preg_match_all($pattern, $noteText, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $exclusionText = $match[1] ?? $match[0];
                    $parsed = $this->parseExclusionText($exclusionText, $sourceChapter, $countryId);
                    if ($parsed) {
                        $exclusions[] = $parsed;
                    }
                }
            }
        }

        // Also look for list items like "(a) Millstones (Chapter 68)"
        $listPattern = '/\([a-z]\)\s*([^;]+?)(?:\((?:heading|chapter)\s*(?:No\.?)?\s*(\d{2}(?:\.\d{2})?)\))?[;,]?/i';
        if (preg_match_all($listPattern, $noteText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $itemText = trim($match[1]);
                $targetRef = $match[2] ?? null;
                
                if (strlen($itemText) > 3) { // Skip very short matches
                    $exclusion = $this->buildExclusionFromItem($itemText, $targetRef, $sourceChapter, $countryId);
                    if ($exclusion) {
                        $exclusions[] = $exclusion;
                    }
                }
            }
        }

        return $exclusions;
    }

    /**
     * Parse exclusion text to extract pattern and target
     */
    protected function parseExclusionText(string $text, TariffChapter $sourceChapter, int $countryId): ?array
    {
        $text = trim($text);
        if (empty($text) || strlen($text) < 5) {
            return null;
        }

        // Look for chapter/heading reference in the text
        $targetChapterId = null;
        $targetHeading = null;

        if (preg_match('/(?:heading|chapter)\s*(?:No\.?)?\s*(\d{2})(?:\.(\d{2}))?/i', $text, $refMatch)) {
            $chapterNum = $refMatch[1];
            $targetHeading = isset($refMatch[2]) ? "{$chapterNum}.{$refMatch[2]}" : null;
            
            $targetChapter = TariffChapter::where('chapter_number', $chapterNum)
                ->where('country_id', $countryId)
                ->first();
            
            if ($targetChapter) {
                $targetChapterId = $targetChapter->id;
            }
        }

        // Extract keywords for the exclusion pattern
        $pattern = $this->extractKeywordsAsPattern($text);
        
        if (empty($pattern)) {
            return null;
        }

        return [
            'country_id' => $countryId,
            'source_chapter_id' => $sourceChapter->id,
            'exclusion_pattern' => $pattern,
            'target_chapter_id' => $targetChapterId,
            'target_heading' => $targetHeading,
            'rule_text' => $this->cleanRuleText($text),
            'priority' => $targetChapterId ? 10 : 5, // Higher priority if we know the target
        ];
    }

    /**
     * Build exclusion from a list item
     */
    protected function buildExclusionFromItem(string $itemText, ?string $targetRef, TariffChapter $sourceChapter, int $countryId): ?array
    {
        $targetChapterId = null;
        $targetHeading = null;

        if ($targetRef) {
            $chapterNum = substr($targetRef, 0, 2);
            $targetChapter = TariffChapter::where('chapter_number', $chapterNum)
                ->where('country_id', $countryId)
                ->first();
            
            if ($targetChapter) {
                $targetChapterId = $targetChapter->id;
            }
            
            if (strlen($targetRef) > 2) {
                $targetHeading = $targetRef;
            }
        }

        $pattern = $this->extractKeywordsAsPattern($itemText);
        
        if (empty($pattern)) {
            return null;
        }

        return [
            'country_id' => $countryId,
            'source_chapter_id' => $sourceChapter->id,
            'exclusion_pattern' => $pattern,
            'target_chapter_id' => $targetChapterId,
            'target_heading' => $targetHeading,
            'rule_text' => $this->cleanRuleText($itemText),
            'priority' => $targetChapterId ? 10 : 5,
        ];
    }

    /**
     * Extract keywords from text to create a matching pattern
     */
    protected function extractKeywordsAsPattern(string $text): string
    {
        // Remove chapter/heading references
        $text = preg_replace('/\(?(?:heading|chapter)\s*(?:No\.?)?\s*\d{2}(?:\.\d{2})?\)?/i', '', $text);
        
        // Remove common stop words and clean up
        $stopWords = ['the', 'of', 'and', 'or', 'a', 'an', 'in', 'to', 'for', 'with', 'as', 'at', 'by', 'on', 'is', 'are'];
        
        // Extract significant words
        preg_match_all('/\b([a-z]{3,})\b/i', $text, $matches);
        $words = array_map('strtolower', $matches[1] ?? []);
        $words = array_diff($words, $stopWords);
        $words = array_unique($words);
        
        if (empty($words)) {
            return '';
        }

        // Take up to 3 most significant words
        $words = array_slice($words, 0, 3);
        
        // Create a pattern - for single word use wildcard, for multiple use the first word
        if (count($words) === 1) {
            return $words[0] . '*';
        }
        
        return implode(' ', $words);
    }

    /**
     * Clean rule text for storage
     */
    protected function cleanRuleText(string $text): string
    {
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        // Trim and limit length
        $text = trim($text);
        return substr($text, 0, 500);
    }

    /**
     * Create or update an exclusion rule
     */
    protected function createOrUpdateExclusion(array $data): ClassificationExclusion
    {
        return ClassificationExclusion::updateOrCreate(
            [
                'country_id' => $data['country_id'],
                'source_chapter_id' => $data['source_chapter_id'],
                'exclusion_pattern' => $data['exclusion_pattern'],
            ],
            $data
        );
    }

    /**
     * Use AI to parse complex exclusion rules
     */
    public function parseWithAI(string $noteText, TariffChapter $sourceChapter, int $countryId, string $apiKey): array
    {
        $prompt = $this->buildAIPrompt($noteText, $sourceChapter);
        
        $response = \Illuminate\Support\Facades\Http::withoutVerifying()
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.claude.model', 'claude-sonnet-4-20250514'),
                'max_tokens' => 2000,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (!$response->successful()) {
            Log::error('AI exclusion parsing failed', ['error' => $response->body()]);
            return [];
        }

        $result = $response->json();
        $content = $result['content'][0]['text'] ?? '';
        
        return $this->parseAIResponse($content, $sourceChapter, $countryId);
    }

    /**
     * Build AI prompt for exclusion parsing
     */
    protected function buildAIPrompt(string $noteText, TariffChapter $sourceChapter): string
    {
        return <<<PROMPT
You are analyzing a customs tariff chapter note to extract exclusion rules.

CHAPTER: {$sourceChapter->formatted_identifier} - {$sourceChapter->title}

NOTE TEXT:
{$noteText}

Extract all exclusion rules from this note. For each exclusion, identify:
1. What items/goods are excluded
2. Where they should be classified instead (if mentioned)

Return as a JSON array:
[
    {
        "excluded_item": "description of what is excluded",
        "keywords": ["keyword1", "keyword2"],
        "target_chapter": "84" or null,
        "target_heading": "84.17" or null
    }
]

Only return the JSON array, no other text.
PROMPT;
    }

    /**
     * Parse AI response into exclusion data
     */
    protected function parseAIResponse(string $content, TariffChapter $sourceChapter, int $countryId): array
    {
        // Extract JSON from response
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $content = $matches[1];
        }

        $items = json_decode(trim($content), true);
        
        if (!is_array($items)) {
            return [];
        }

        $exclusions = [];
        
        foreach ($items as $item) {
            $keywords = $item['keywords'] ?? [];
            $pattern = !empty($keywords) ? implode(' ', array_slice($keywords, 0, 3)) : ($item['excluded_item'] ?? '');
            
            if (empty($pattern)) {
                continue;
            }

            $targetChapterId = null;
            if (!empty($item['target_chapter'])) {
                $targetChapter = TariffChapter::where('chapter_number', $item['target_chapter'])
                    ->where('country_id', $countryId)
                    ->first();
                $targetChapterId = $targetChapter?->id;
            }

            $exclusions[] = [
                'country_id' => $countryId,
                'source_chapter_id' => $sourceChapter->id,
                'exclusion_pattern' => $pattern,
                'target_chapter_id' => $targetChapterId,
                'target_heading' => $item['target_heading'] ?? null,
                'rule_text' => $item['excluded_item'] ?? $pattern,
                'priority' => $targetChapterId ? 10 : 5,
            ];
        }

        return $exclusions;
    }
}
