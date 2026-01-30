<?php

namespace App\Services\WebFormSubmission;

use App\Models\CountryReferenceData;
use App\Models\DeclarationForm;
use App\Models\WebFormTarget;
use App\Services\ClaudeJsonClient;
use Illuminate\Support\Facades\Log;

/**
 * CapsAIMapper
 * 
 * Uses Claude AI to intelligently map declaration data to CAPS field values.
 * Handles fuzzy matching for dropdowns, codes, and reference data.
 */
class CapsAIMapper
{
    protected ClaudeJsonClient $claude;
    protected ?int $countryId = null;
    protected array $referenceCache = [];
    protected array $aiDecisions = [];

    public function __construct(ClaudeJsonClient $claude)
    {
        $this->claude = $claude;
    }

    /**
     * Set the country ID for reference data lookups
     */
    public function setCountryId(int $countryId): self
    {
        $this->countryId = $countryId;
        return $this;
    }

    /**
     * Get all AI decisions made during mapping
     */
    public function getAiDecisions(): array
    {
        return $this->aiDecisions;
    }

    /**
     * Clear cached decisions
     */
    public function clearDecisions(): void
    {
        $this->aiDecisions = [];
    }

    /**
     * Map a value to a CAPS reference code using AI when needed
     */
    public function mapToReference(string $type, ?string $value, string $fieldName = ''): ?string
    {
        if (empty($value) || !$this->countryId) {
            return $value;
        }

        // First try exact match from reference data
        $exactMatch = CountryReferenceData::findByLocalMatch($this->countryId, $type, $value);
        if ($exactMatch) {
            Log::debug("CapsAIMapper: Exact match for {$fieldName}", [
                'input' => $value,
                'matched' => $exactMatch->code,
            ]);
            return $exactMatch->code;
        }

        // No exact match - use Claude for fuzzy matching
        $referenceOptions = $this->getReferenceOptions($type);
        if (empty($referenceOptions)) {
            Log::warning("CapsAIMapper: No reference data for type {$type}");
            return $value;
        }

        // Ask Claude to find the best match
        $aiMatch = $this->askClaudeForMatch($value, $referenceOptions, $fieldName, $type);
        
        if ($aiMatch) {
            $this->aiDecisions[] = [
                'field' => $fieldName,
                'type' => $type,
                'input_value' => $value,
                'matched_code' => $aiMatch['code'],
                'matched_label' => $aiMatch['label'] ?? '',
                'confidence' => $aiMatch['confidence'] ?? 'medium',
                'reasoning' => $aiMatch['reasoning'] ?? '',
            ];
            
            Log::info("CapsAIMapper: AI matched {$fieldName}", [
                'input' => $value,
                'matched' => $aiMatch['code'],
                'confidence' => $aiMatch['confidence'] ?? 'medium',
            ]);
            
            return $aiMatch['code'];
        }

        Log::warning("CapsAIMapper: No match found for {$fieldName}", ['input' => $value]);
        return $value;
    }

    /**
     * Get reference options for a type (cached)
     */
    protected function getReferenceOptions(string $type): array
    {
        $cacheKey = "{$this->countryId}_{$type}";
        
        if (!isset($this->referenceCache[$cacheKey])) {
            $records = CountryReferenceData::where('country_id', $this->countryId)
                ->where('reference_type', $type)
                ->active()
                ->ordered()
                ->get();
            
            $this->referenceCache[$cacheKey] = $records->map(fn($r) => [
                'code' => $r->code,
                'label' => $r->label,
                'local_matches' => $r->local_matches ?? [],
            ])->toArray();
        }
        
        return $this->referenceCache[$cacheKey];
    }

    /**
     * Ask Claude to find the best matching reference code
     */
    protected function askClaudeForMatch(string $inputValue, array $options, string $fieldName, string $type): ?array
    {
        // Format options for Claude
        $optionsList = array_map(fn($o) => "{$o['code']} - {$o['label']}", $options);
        $optionsText = implode("\n", $optionsList);

        $prompt = <<<PROMPT
You are a customs data mapper for BVI (British Virgin Islands) CAPS system.

I need to match a value to the correct CAPS reference code.

**Field:** {$fieldName}
**Reference Type:** {$type}
**Input Value:** "{$inputValue}"

**Available Options:**
{$optionsText}

**Instructions:**
1. Find the BEST matching code for the input value
2. Consider common abbreviations, variations, and synonyms
3. For countries, match by name, ISO code, or common variations
4. For carriers, match shipping company names to their codes
5. For ports, match by city name, port name, or code
6. If no good match exists, return null

**Return JSON only:**
{
  "code": "THE_CODE" or null,
  "label": "The matched label",
  "confidence": "high" | "medium" | "low",
  "reasoning": "Brief explanation of the match"
}
PROMPT;

        try {
            $result = $this->claude->promptForJson($prompt, 30, 200);
            
            if (!empty($result['code'])) {
                // Verify the code exists in options
                $validCodes = array_column($options, 'code');
                if (in_array($result['code'], $validCodes)) {
                    return $result;
                }
            }
        } catch (\Exception $e) {
            Log::error("CapsAIMapper: Claude API error", [
                'error' => $e->getMessage(),
                'field' => $fieldName,
            ]);
        }

        return null;
    }

    /**
     * Map all values in a declaration header to CAPS codes
     */
    public function mapHeaderData(array $headerData): array
    {
        $mapped = $headerData;

        // Map carrier
        if (!empty($headerData['carrier_id'])) {
            $mapped['carrier_id'] = $this->mapToReference(
                CountryReferenceData::TYPE_CARRIER,
                $headerData['carrier_id'],
                'Carrier ID'
            );
        }

        // Map port of arrival
        if (!empty($headerData['port_of_arrival'])) {
            $mapped['port_of_arrival'] = $this->mapToReference(
                CountryReferenceData::TYPE_PORT,
                $headerData['port_of_arrival'],
                'Port of Arrival'
            );
        }

        // Map supplier country
        if (!empty($headerData['supplier_country'])) {
            $mapped['supplier_country'] = $this->mapToReference(
                CountryReferenceData::TYPE_COUNTRY,
                $headerData['supplier_country'],
                'Supplier Country'
            );
        }

        // Map country of direct shipment
        if (!empty($headerData['country_of_direct_shipment'])) {
            $mapped['country_of_direct_shipment'] = $this->mapToReference(
                CountryReferenceData::TYPE_COUNTRY,
                $headerData['country_of_direct_shipment'],
                'Country of Direct Shipment'
            );
        }

        // Map country of original shipment
        if (!empty($headerData['country_of_origin_shipment'])) {
            $mapped['country_of_origin_shipment'] = $this->mapToReference(
                CountryReferenceData::TYPE_COUNTRY,
                $headerData['country_of_origin_shipment'],
                'Country of Original Shipment'
            );
        }

        return $mapped;
    }

    /**
     * Map all values in declaration items to CAPS codes
     */
    public function mapItemsData(array $items): array
    {
        return array_map(function ($item, $index) {
            $itemNum = $index + 1;
            $mapped = $item;

            // Map CPC code
            if (!empty($item['cpc'])) {
                $mapped['cpc'] = $this->mapToReference(
                    CountryReferenceData::TYPE_CPC,
                    $item['cpc'],
                    "Item {$itemNum} CPC"
                );
            }

            // Map country of origin
            if (!empty($item['country_of_origin'])) {
                $mapped['country_of_origin'] = $this->mapToReference(
                    CountryReferenceData::TYPE_COUNTRY,
                    $item['country_of_origin'],
                    "Item {$itemNum} Country of Origin"
                );
            }

            // Map currency
            if (!empty($item['currency'])) {
                $mapped['currency'] = $this->mapToReference(
                    CountryReferenceData::TYPE_CURRENCY,
                    $item['currency'],
                    "Item {$itemNum} Currency"
                );
            }

            // Map units
            if (!empty($item['units'])) {
                $mapped['units'] = $this->mapToReference(
                    CountryReferenceData::TYPE_UNIT,
                    $item['units'],
                    "Item {$itemNum} Units"
                );
            }

            // Map tax types
            if (!empty($item['tax_type_1'])) {
                $mapped['tax_type_1'] = $this->mapToReference(
                    CountryReferenceData::TYPE_TAX_TYPE,
                    $item['tax_type_1'],
                    "Item {$itemNum} Tax Type 1"
                );
            }
            if (!empty($item['tax_type_2'])) {
                $mapped['tax_type_2'] = $this->mapToReference(
                    CountryReferenceData::TYPE_TAX_TYPE,
                    $item['tax_type_2'],
                    "Item {$itemNum} Tax Type 2"
                );
            }

            // Map charge codes
            if (!empty($item['freight_code'])) {
                $mapped['freight_code'] = $this->mapToReference(
                    CountryReferenceData::TYPE_CHARGE_CODE,
                    $item['freight_code'],
                    "Item {$itemNum} Freight Code"
                );
            }
            if (!empty($item['insurance_code'])) {
                $mapped['insurance_code'] = $this->mapToReference(
                    CountryReferenceData::TYPE_CHARGE_CODE,
                    $item['insurance_code'],
                    "Item {$itemNum} Insurance Code"
                );
            }

            return $mapped;
        }, $items, array_keys($items));
    }

    /**
     * Analyze a complete CAPS submission and provide AI-guided recommendations
     */
    public function analyzeSubmission(array $headerData, array $items): array
    {
        // Map header data
        $mappedHeader = $this->mapHeaderData($headerData);
        
        // Map items data  
        $mappedItems = $this->mapItemsData($items);

        return [
            'headerData' => $mappedHeader,
            'items' => $mappedItems,
            'ai_decisions' => $this->aiDecisions,
            'summary' => [
                'total_ai_mappings' => count($this->aiDecisions),
                'high_confidence' => count(array_filter($this->aiDecisions, fn($d) => ($d['confidence'] ?? '') === 'high')),
                'medium_confidence' => count(array_filter($this->aiDecisions, fn($d) => ($d['confidence'] ?? '') === 'medium')),
                'low_confidence' => count(array_filter($this->aiDecisions, fn($d) => ($d['confidence'] ?? '') === 'low')),
            ],
        ];
    }

    /**
     * Interpret CAPS validation errors and suggest fixes using AI
     */
    public function interpretValidationErrors(array $errors): array
    {
        if (empty($errors)) {
            return [];
        }

        $errorsText = implode("\n", $errors);
        
        $prompt = <<<PROMPT
You are analyzing CAPS (Customs) validation errors for BVI (British Virgin Islands).

**Validation Errors:**
{$errorsText}

**Instructions:**
Analyze each error and provide actionable suggestions to fix it.

**Return JSON array:**
[
  {
    "error": "Original error text",
    "category": "data_missing" | "invalid_code" | "calculation_error" | "format_error" | "reference_error",
    "severity": "critical" | "warning" | "info",
    "suggestion": "How to fix this error",
    "field_to_check": "Which field needs attention"
  }
]
PROMPT;

        try {
            $result = $this->claude->promptForJson($prompt, 30, 500);
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            Log::error("CapsAIMapper: Error interpreting validation errors", ['error' => $e->getMessage()]);
            return [];
        }
    }
}
