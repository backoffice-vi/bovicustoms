<?php

namespace App\Services;

use App\Models\TradeContact;
use App\Models\Country;
use Illuminate\Support\Collection;
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

        $companyName = $partyDetails['company_name'];
        
        // Get all contacts of this type for the current tenant
        $contacts = TradeContact::ofType($contactType)->get();
        
        if ($contacts->isEmpty()) {
            return [
                'matches' => [],
                'best_match' => null,
                'confidence' => 0,
                'exact_match' => false,
            ];
        }

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

        // Sort by score descending
        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

        $bestMatch = $matches[0] ?? null;

        return [
            'matches' => $matches,
            'best_match' => $bestMatch ? $bestMatch['contact'] : null,
            'confidence' => $bestMatch ? $bestMatch['score'] : 0,
            'exact_match' => $bestMatch && $bestMatch['score'] >= 95,
            'is_high_confidence' => $bestMatch && $bestMatch['is_high_confidence'],
        ];
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
        // Normalize strings
        $str1 = $this->normalizeForComparison($str1);
        $str2 = $this->normalizeForComparison($str2);

        // Exact match
        if ($str1 === $str2) {
            return 100;
        }

        // Check if one contains the other (partial match)
        if (str_contains($str1, $str2) || str_contains($str2, $str1)) {
            $lengthRatio = min(strlen($str1), strlen($str2)) / max(strlen($str1), strlen($str2));
            return (int) round(80 + (20 * $lengthRatio));
        }

        // Use similar_text for fuzzy matching
        similar_text($str1, $str2, $percent);
        
        // Also use levenshtein for short strings
        if (strlen($str1) < 50 && strlen($str2) < 50) {
            $maxLen = max(strlen($str1), strlen($str2));
            $levenshtein = levenshtein($str1, $str2);
            $levenshteinScore = $maxLen > 0 ? (1 - ($levenshtein / $maxLen)) * 100 : 0;
            
            // Use the better of the two scores
            return (int) round(max($percent, $levenshteinScore));
        }

        return (int) round($percent);
    }

    /**
     * Normalize string for comparison
     */
    protected function normalizeForComparison(string $str): string
    {
        // Lowercase
        $str = mb_strtolower($str);
        
        // Remove common business suffixes
        $suffixes = ['ltd', 'llc', 'inc', 'corp', 'co', 'company', 'limited', 'corporation'];
        foreach ($suffixes as $suffix) {
            $str = preg_replace('/\b' . $suffix . '\.?\s*$/i', '', $str);
        }
        
        // Remove punctuation and extra spaces
        $str = preg_replace('/[^\w\s]/', '', $str);
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
