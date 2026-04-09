<?php

namespace App\Services;

use App\Models\TradeContact;
use App\Models\Country;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContactMatchingService
{
    /**
     * Minimum similarity score to consider a match (0-100)
     */
    const MIN_MATCH_SCORE = 60;

    /**
     * High confidence threshold (0-100)
     */
    const HIGH_CONFIDENCE_SCORE = 85;

    /**
     * Find matching contacts for extracted party details
     *
     * @param array|null $partyDetails Extracted details (company_name, address, etc.)
     * @param string $contactType TradeContact type (shipper, consignee, etc.)
     * @return array Contains 'matches' array and 'best_match' if found
     */
    public function findMatches(?array $partyDetails, string $contactType): array
    {
        if (!$partyDetails || empty($partyDetails['company_name'])) {
            return [
                'matches' => [],
                'best_match' => null,
                'confidence' => 0,
                'exact_match' => false,
            ];
        }

        $contacts = TradeContact::ofType($contactType)->get();
        
        if ($contacts->isEmpty()) {
            return [
                'matches' => [],
                'best_match' => null,
                'confidence' => 0,
                'exact_match' => false,
            ];
        }

        // Step 1: Fuzzy string matching (fast, free)
        $matches = [];
        foreach ($contacts as $contact) {
            $score = $this->calculateMatchScore($partyDetails, $contact);
            if ($score >= self::MIN_MATCH_SCORE) {
                $matches[] = [
                    'contact' => $contact,
                    'score' => $score,
                    'is_high_confidence' => $score >= self::HIGH_CONFIDENCE_SCORE,
                ];
            }
        }

        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
        $bestMatch = $matches[0] ?? null;

        // Step 2: AI matching — runs when fuzzy matching found no high-confidence match
        $needsAi = !$bestMatch || !$bestMatch['is_high_confidence'];
        if ($needsAi) {
            $aiResult = $this->matchWithAI($partyDetails, $contacts);

            if ($aiResult && $aiResult['contact_id']) {
                $aiContact = $contacts->firstWhere('id', $aiResult['contact_id']);
                if ($aiContact) {
                    $aiScore = $aiResult['confidence'];
                    $existingIdx = collect($matches)->search(fn($m) => $m['contact']->id === $aiContact->id);

                    if ($existingIdx !== false) {
                        // AI confirmed a fuzzy match — boost its score
                        $boosted = max($matches[$existingIdx]['score'], $aiScore);
                        $matches[$existingIdx]['score'] = $boosted;
                        $matches[$existingIdx]['is_high_confidence'] = $boosted >= self::HIGH_CONFIDENCE_SCORE;
                        $matches[$existingIdx]['ai_confirmed'] = true;
                    } else {
                        // AI found a match that fuzzy missed entirely
                        $matches[] = [
                            'contact' => $aiContact,
                            'score' => $aiScore,
                            'is_high_confidence' => $aiScore >= self::HIGH_CONFIDENCE_SCORE,
                            'ai_matched' => true,
                        ];
                    }

                    usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
                    $bestMatch = $matches[0];
                }
            }
        }

        return [
            'matches' => $matches,
            'best_match' => $bestMatch ? $bestMatch['contact'] : null,
            'confidence' => $bestMatch ? $bestMatch['score'] : 0,
            'exact_match' => $bestMatch && $bestMatch['score'] >= 95,
            'is_high_confidence' => $bestMatch && $bestMatch['is_high_confidence'],
        ];
    }

    /**
     * Use AI to determine if the extracted contact matches any existing contact.
     * Returns the best matching contact_id and confidence, or null.
     */
    protected function matchWithAI(array $partyDetails, Collection $contacts): ?array
    {
        $apiKey = config('services.claude.api_key');
        $model = config('services.claude.model');
        if (!$apiKey) {
            return null;
        }

        $extractedSummary = $partyDetails['company_name'];
        if (!empty($partyDetails['address'])) $extractedSummary .= ', ' . $partyDetails['address'];
        if (!empty($partyDetails['city'])) $extractedSummary .= ', ' . $partyDetails['city'];
        if (!empty($partyDetails['country'])) $extractedSummary .= ', ' . $partyDetails['country'];
        if (!empty($partyDetails['phone'])) $extractedSummary .= ', Phone: ' . $partyDetails['phone'];

        $contactList = '';
        foreach ($contacts as $c) {
            $addr = implode(', ', array_filter([
                $c->address_line_1, $c->city, $c->state_province, $c->phone,
            ]));
            $contactList .= "  ID {$c->id}: {$c->company_name}" . ($addr ? " ({$addr})" : '') . "\n";
        }

        $prompt = <<<PROMPT
You are a trade/shipping contact matching assistant. Determine if the extracted contact from a shipping document matches any existing contact in the database.

EXTRACTED CONTACT (from Bill of Lading):
{$extractedSummary}

EXISTING DATABASE CONTACTS:
{$contactList}

MATCHING RULES:
- Company names may differ slightly: abbreviations (Ltd/Limited), formatting (dashes, spaces), extra location info (e.g., "East End" branch suffix)
- "KEHE DISTRIBUTORS-TREE OF LIFE" and "Kehe Distributors - Tree of Life" are the SAME company
- "NATURE'S WAY LTD" and "Nature's Way East End" are the SAME company (East End is a location/branch)
- Phone numbers may have different formatting but same digits
- Addresses may be partial or formatted differently
- If the core business name matches, it's likely the same contact even if address details differ

Return ONLY a JSON object:
{
  "match_found": true,
  "contact_id": 4,
  "confidence": 92,
  "reasoning": "Brief explanation"
}

If NO contact matches, return:
{
  "match_found": false,
  "contact_id": null,
  "confidence": 0,
  "reasoning": "No matching contact found"
}
PROMPT;

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(15)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 256,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);

            if (!$response->successful()) {
                Log::warning('AI contact matching API failed', ['status' => $response->status()]);
                return null;
            }

            $text = $response->json()['content'][0]['text'] ?? '';
            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $text, $m)) {
                $text = $m[1];
            }

            $result = json_decode(trim($text), true);
            if (!is_array($result) || !($result['match_found'] ?? false)) {
                return null;
            }

            $contactId = $result['contact_id'] ?? null;
            $confidence = (int) ($result['confidence'] ?? 0);

            if (!$contactId || $confidence < 50) {
                return null;
            }

            Log::info('AI contact match found', [
                'extracted' => $partyDetails['company_name'],
                'matched_id' => $contactId,
                'confidence' => $confidence,
                'reasoning' => $result['reasoning'] ?? '',
            ]);

            return [
                'contact_id' => $contactId,
                'confidence' => $confidence,
                'reasoning' => $result['reasoning'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::warning('AI contact matching failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Calculate match score between extracted details and a contact
     *
     * @param array $partyDetails
     * @param TradeContact $contact
     * @return int Score from 0-100
     */
    protected function calculateMatchScore(array $partyDetails, TradeContact $contact): int
    {
        $scores = [];
        $weights = [];

        // Company name is most important
        if (!empty($partyDetails['company_name'])) {
            $scores['company'] = $this->similarityScore(
                $partyDetails['company_name'],
                $contact->company_name
            );
            $weights['company'] = 50;
        }

        // City match
        if (!empty($partyDetails['city']) && $contact->city) {
            $scores['city'] = $this->similarityScore(
                $partyDetails['city'],
                $contact->city
            );
            $weights['city'] = 15;
        }

        // Country match
        if (!empty($partyDetails['country'])) {
            $countryScore = $this->matchCountry($partyDetails['country'], $contact);
            if ($countryScore !== null) {
                $scores['country'] = $countryScore;
                $weights['country'] = 15;
            }
        }

        // Phone match (if available, high confidence indicator)
        if (!empty($partyDetails['phone']) && $contact->phone) {
            $scores['phone'] = $this->phoneMatch(
                $partyDetails['phone'],
                $contact->phone
            );
            $weights['phone'] = 20;
        }

        // Address partial match
        if (!empty($partyDetails['address']) && $contact->address_line_1) {
            $scores['address'] = $this->similarityScore(
                $partyDetails['address'],
                $contact->address_line_1
            );
            $weights['address'] = 10;
        }

        // Calculate weighted average
        if (empty($scores)) {
            return 0;
        }

        $totalWeight = array_sum($weights);
        $weightedSum = 0;
        
        foreach ($scores as $key => $score) {
            $weightedSum += $score * ($weights[$key] ?? 0);
        }

        return (int) round($weightedSum / $totalWeight);
    }

    /**
     * Calculate similarity score between two strings
     *
     * @param string $str1
     * @param string $str2
     * @return int Score from 0-100
     */
    protected function similarityScore(string $str1, string $str2): int
    {
        $str1 = $this->normalizeForComparison($str1);
        $str2 = $this->normalizeForComparison($str2);

        if ($str1 === $str2) {
            return 100;
        }

        // Check if one contains the other (partial match)
        if (str_contains($str1, $str2) || str_contains($str2, $str1)) {
            $lengthRatio = min(strlen($str1), strlen($str2)) / max(strlen($str1), strlen($str2));
            return (int) round(80 + (20 * $lengthRatio));
        }

        // Token-based matching: what fraction of the shorter name's words appear in the longer?
        $tokens1 = array_filter(explode(' ', $str1), fn($w) => strlen($w) > 1);
        $tokens2 = array_filter(explode(' ', $str2), fn($w) => strlen($w) > 1);
        if (!empty($tokens1) && !empty($tokens2)) {
            $shorter = count($tokens1) <= count($tokens2) ? $tokens1 : $tokens2;
            $longer = count($tokens1) > count($tokens2) ? $tokens1 : $tokens2;
            $hits = 0;
            foreach ($shorter as $token) {
                foreach ($longer as $lToken) {
                    if ($token === $lToken || str_contains($lToken, $token) || str_contains($token, $lToken)) {
                        $hits++;
                        break;
                    }
                }
            }
            $tokenScore = (int) round(($hits / count($shorter)) * 100);
        }

        // Use similar_text for fuzzy matching
        similar_text($str1, $str2, $percent);
        
        // Also use levenshtein for short strings
        $levenshteinScore = 0;
        if (strlen($str1) < 50 && strlen($str2) < 50) {
            $maxLen = max(strlen($str1), strlen($str2));
            $levenshtein = levenshtein($str1, $str2);
            $levenshteinScore = $maxLen > 0 ? (1 - ($levenshtein / $maxLen)) * 100 : 0;
        }

        return (int) round(max($percent, $levenshteinScore, $tokenScore ?? 0));
    }

    /**
     * Normalize string for comparison
     */
    protected function normalizeForComparison(string $str): string
    {
        $str = mb_strtolower($str);
        
        // Remove common business suffixes
        $suffixes = ['ltd', 'llc', 'inc', 'corp', 'co', 'company', 'limited', 'corporation'];
        foreach ($suffixes as $suffix) {
            $str = preg_replace('/\b' . $suffix . '\.?\b/i', '', $str);
        }
        
        // Replace punctuation with spaces (not remove) so "DISTRIBUTORS-TREE" becomes "distributors tree"
        $str = preg_replace('/[^\w\s]/', ' ', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        
        return trim($str);
    }

    /**
     * Match country by name or code
     */
    protected function matchCountry(string $countryInput, TradeContact $contact): ?int
    {
        if (!$contact->country_id) {
            return null;
        }

        $country = $contact->country;
        if (!$country) {
            return null;
        }

        $input = mb_strtolower(trim($countryInput));
        $countryName = mb_strtolower($country->name);
        $countryCode = mb_strtolower($country->code);

        // Exact matches
        if ($input === $countryName || $input === $countryCode) {
            return 100;
        }

        // Partial name match
        if (str_contains($countryName, $input) || str_contains($input, $countryName)) {
            return 80;
        }

        // Check common abbreviations
        $abbreviations = $this->getCountryAbbreviations();
        if (isset($abbreviations[$input]) && $abbreviations[$input] === $countryCode) {
            return 100;
        }

        return 0;
    }

    /**
     * Match phone numbers
     */
    protected function phoneMatch(string $phone1, string $phone2): int
    {
        // Normalize to digits only
        $digits1 = preg_replace('/\D/', '', $phone1);
        $digits2 = preg_replace('/\D/', '', $phone2);

        if ($digits1 === $digits2) {
            return 100;
        }

        // Check if one ends with the other (handles different country code formats)
        if (str_ends_with($digits1, $digits2) || str_ends_with($digits2, $digits1)) {
            return 90;
        }

        // Check last 7 digits (local number)
        $local1 = substr($digits1, -7);
        $local2 = substr($digits2, -7);
        if ($local1 === $local2) {
            return 80;
        }

        return 0;
    }

    /**
     * Get common country abbreviations
     */
    protected function getCountryAbbreviations(): array
    {
        return [
            'usa' => 'us',
            'u.s.a.' => 'us',
            'united states' => 'us',
            'uk' => 'gb',
            'u.k.' => 'gb',
            'britain' => 'gb',
            'pr' => 'pr',
            'puerto rico' => 'pr',
            'bvi' => 'vg',
            'virgin islands' => 'vg',
            'british virgin islands' => 'vg',
        ];
    }

    /**
     * Create a new contact from extracted party details
     *
     * @param array $partyDetails
     * @param string $contactType
     * @param int|null $countryId
     * @return TradeContact
     */
    public function createContactFromDetails(array $partyDetails, string $contactType, ?int $countryId = null): TradeContact
    {
        $user = auth()->user();

        // Try to determine country if not provided
        if (!$countryId && !empty($partyDetails['country'])) {
            $country = Country::where('name', 'like', '%' . $partyDetails['country'] . '%')
                ->orWhere('code', strtoupper($partyDetails['country']))
                ->first();
            $countryId = $country?->id;
        }

        return TradeContact::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'contact_type' => $contactType,
            'company_name' => $partyDetails['company_name'] ?? null,
            'address_line_1' => $partyDetails['address'] ?? null,
            'city' => $partyDetails['city'] ?? null,
            'state_province' => $partyDetails['state_province'] ?? null,
            'postal_code' => $partyDetails['postal_code'] ?? null,
            'country_id' => $countryId,
            'phone' => $partyDetails['phone'] ?? null,
            'fax' => $partyDetails['fax'] ?? null,
            'email' => $partyDetails['email'] ?? null,
            'is_default' => false,
        ]);
    }

    /**
     * Get suggestions for a partial company name
     */
    public function searchContacts(string $query, ?string $contactType = null, int $limit = 10): Collection
    {
        $query = TradeContact::query();

        if ($contactType) {
            $query->ofType($contactType);
        }

        return $query
            ->where(function ($q) use ($query) {
                $q->where('company_name', 'like', "%{$query}%")
                  ->orWhere('contact_name', 'like', "%{$query}%");
            })
            ->orderBy('company_name')
            ->limit($limit)
            ->get();
    }

    /**
     * Prepare match result for API response
     */
    public function formatMatchResult(array $matchResult): array
    {
        $formatted = [
            'has_matches' => !empty($matchResult['matches']),
            'exact_match' => $matchResult['exact_match'] ?? false,
            'is_high_confidence' => $matchResult['is_high_confidence'] ?? false,
            'confidence' => $matchResult['confidence'] ?? 0,
            'best_match' => null,
            'matches' => [],
        ];

        if ($matchResult['best_match']) {
            $formatted['best_match'] = [
                'id' => $matchResult['best_match']->id,
                'company_name' => $matchResult['best_match']->company_name,
                'full_address' => $matchResult['best_match']->full_address,
                'phone' => $matchResult['best_match']->phone,
                'confidence' => $matchResult['confidence'],
            ];
        }

        foreach ($matchResult['matches'] as $match) {
            $formatted['matches'][] = [
                'id' => $match['contact']->id,
                'company_name' => $match['contact']->company_name,
                'full_address' => $match['contact']->full_address,
                'phone' => $match['contact']->phone,
                'score' => $match['score'],
                'is_high_confidence' => $match['is_high_confidence'],
            ];
        }

        return $formatted;
    }
}
