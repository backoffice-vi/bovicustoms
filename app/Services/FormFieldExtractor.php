<?php

namespace App\Services;

use App\Models\CountryFormTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FormFieldExtractor
{
    protected ClaudeJsonClient $claude;
    protected DocumentTextExtractor $textExtractor;

    public function __construct(ClaudeJsonClient $claude, DocumentTextExtractor $textExtractor)
    {
        $this->claude = $claude;
        $this->textExtractor = $textExtractor;
    }

    /**
     * Extract all fillable fields from a form template using AI
     */
    public function extractFields(CountryFormTemplate $template): array
    {
        try {
            // Get the full path to the template file
            $fullPath = $template->getFullPath();
            
            if (!file_exists($fullPath)) {
                Log::error('Form template file not found', ['path' => $fullPath]);
                return $this->errorResult('Template file not found');
            }

            // Extract text from the document
            $text = $this->textExtractor->extractText($fullPath, $template->file_type);
            
            if (empty(trim($text))) {
                Log::warning('Empty text extracted from template', ['template_id' => $template->id]);
                return $this->errorResult('Could not extract text from template');
            }

            // Use AI to analyze the form and extract fields
            $prompt = $this->buildExtractionPrompt($text, $template);
            $result = $this->claude->promptForJson($prompt, 180);

            if (empty($result)) {
                return $this->errorResult('AI could not parse the form structure');
            }

            // Validate and normalize the result
            return $this->normalizeResult($result, $template);

        } catch (\Exception $e) {
            Log::error('Form field extraction failed', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
            return $this->errorResult('Extraction failed: ' . $e->getMessage());
        }
    }

    /**
     * Build the AI prompt for field extraction
     */
    protected function buildExtractionPrompt(string $text, CountryFormTemplate $template): string
    {
        $formType = $template->form_type_label;
        $countryName = $template->country?->name ?? 'Unknown';

        return <<<PROMPT
You are analyzing a {$formType} form from {$countryName} to extract all fields that need to be filled out.

Here is the text content of the form:
---
{$text}
---

Analyze this form and extract ALL fields that a user would need to fill in. For each field, provide:

1. field_name: A unique, snake_case identifier (e.g., "importer_name", "vessel_name", "port_of_entry")
2. field_label: The human-readable label as it appears on the form
3. field_type: One of: text, number, date, currency, checkbox, select, textarea
4. section: Group name (e.g., "Importer Details", "Shipping Information", "Goods Description")
5. is_required: true/false - is this field typically required?
6. max_length: Maximum characters if applicable (null otherwise)
7. hints: Any helpful hints about what data goes in this field
8. data_source: What type of data would fill this field? Options:
   - "shipper" (company name, address, etc. of shipper/exporter)
   - "consignee" (company name, address, etc. of consignee/importer)
   - "broker" (customs broker details)
   - "bank" (bank/payment information)
   - "invoice" (invoice number, date, amounts)
   - "goods" (item descriptions, quantities, HS codes)
   - "shipping" (vessel, port, bill of lading)
   - "declaration" (declaration number, date)
   - "manual" (user must enter manually)

Return a JSON object with this structure:
{
  "form_title": "The title/name of the form",
  "form_description": "Brief description of what this form is for",
  "sections": [
    {
      "name": "Section Name",
      "fields": [
        {
          "field_name": "unique_field_id",
          "field_label": "Label on Form",
          "field_type": "text",
          "is_required": true,
          "max_length": null,
          "hints": "Helpful hint",
          "data_source": "shipper"
        }
      ]
    }
  ],
  "total_fields": 15
}

Important:
- Extract ALL fillable fields, not just a few
- Group fields logically by section
- Be thorough - customs forms often have many fields
- Include signature/date fields at the end
- Return ONLY valid JSON, no markdown or explanations
PROMPT;
    }

    /**
     * Normalize and validate the AI result
     */
    protected function normalizeResult(array $result, CountryFormTemplate $template): array
    {
        $fields = [];
        $sections = $result['sections'] ?? [];

        foreach ($sections as $section) {
            $sectionName = $section['name'] ?? 'General';
            $sectionFields = $section['fields'] ?? [];

            foreach ($sectionFields as $field) {
                $fields[] = [
                    'field_name' => $this->sanitizeFieldName($field['field_name'] ?? ''),
                    'field_label' => $field['field_label'] ?? 'Unknown Field',
                    'field_type' => $this->validateFieldType($field['field_type'] ?? 'text'),
                    'section' => $sectionName,
                    'is_required' => (bool)($field['is_required'] ?? false),
                    'max_length' => $field['max_length'] ?? null,
                    'hints' => $field['hints'] ?? null,
                    'data_source' => $this->validateDataSource($field['data_source'] ?? 'manual'),
                ];
            }
        }

        return [
            'success' => true,
            'template_id' => $template->id,
            'template_name' => $template->name,
            'form_title' => $result['form_title'] ?? $template->name,
            'form_description' => $result['form_description'] ?? null,
            'sections' => $sections,
            'fields' => $fields,
            'total_fields' => count($fields),
        ];
    }

    /**
     * Sanitize field name to snake_case
     */
    protected function sanitizeFieldName(string $name): string
    {
        // Convert to lowercase, replace spaces/special chars with underscores
        $name = preg_replace('/[^a-zA-Z0-9]+/', '_', strtolower(trim($name)));
        $name = trim($name, '_');
        
        // Ensure it's not empty
        if (empty($name)) {
            $name = 'field_' . uniqid();
        }

        return $name;
    }

    /**
     * Validate field type
     */
    protected function validateFieldType(string $type): string
    {
        $validTypes = ['text', 'number', 'date', 'currency', 'checkbox', 'select', 'textarea'];
        $type = strtolower(trim($type));
        
        return in_array($type, $validTypes) ? $type : 'text';
    }

    /**
     * Validate data source
     */
    protected function validateDataSource(string $source): string
    {
        $validSources = ['shipper', 'consignee', 'broker', 'bank', 'invoice', 'goods', 'shipping', 'declaration', 'manual'];
        $source = strtolower(trim($source));
        
        return in_array($source, $validSources) ? $source : 'manual';
    }

    /**
     * Return error result
     */
    protected function errorResult(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
            'fields' => [],
            'total_fields' => 0,
        ];
    }

    /**
     * Get fields grouped by data source
     */
    public function groupFieldsByDataSource(array $fields): array
    {
        $grouped = [];
        
        foreach ($fields as $field) {
            $source = $field['data_source'] ?? 'manual';
            if (!isset($grouped[$source])) {
                $grouped[$source] = [];
            }
            $grouped[$source][] = $field;
        }

        return $grouped;
    }

    /**
     * Get fields that require specific contact types
     */
    public function getRequiredContactTypes(array $fields): array
    {
        $contactSources = ['shipper', 'consignee', 'broker', 'bank'];
        $required = [];

        foreach ($fields as $field) {
            $source = $field['data_source'] ?? 'manual';
            if (in_array($source, $contactSources) && !in_array($source, $required)) {
                $required[] = $source;
            }
        }

        return $required;
    }
}
