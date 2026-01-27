<?php

namespace App\Services\WebFormSubmission;

use App\Models\DeclarationForm;
use App\Models\WebFormTarget;
use App\Models\WebFormFieldMapping;
use Illuminate\Support\Facades\Log;

/**
 * WebFormDataMapper
 * 
 * Maps data from a DeclarationForm to the field structure expected by
 * an external web form based on stored field mappings.
 */
class WebFormDataMapper
{
    /**
     * Map declaration data to target web form fields
     */
    public function mapDeclarationToTarget(DeclarationForm $declaration, WebFormTarget $target): array
    {
        // Load related data
        $declaration->load([
            'invoice.invoiceItems',
            'shipment.shipperContact',
            'shipment.consigneeContact',
            'shipperContact',
            'consigneeContact',
            'country',
            'declarationItems',
        ]);

        $fields = [];
        $mappings = [];
        $unmappedRequired = [];

        // Get all field mappings for this target
        foreach ($target->pages as $page) {
            foreach ($page->activeFieldMappings as $mapping) {
                $value = $mapping->getValueFromDeclaration($declaration);

                $fieldData = [
                    'name' => $mapping->web_field_label,
                    'field_name' => $mapping->web_field_name,
                    'field_id' => $mapping->web_field_id,
                    'selectors' => $mapping->getSelectorsForPlaywright(),
                    'type' => $mapping->field_type,
                    'value' => $value,
                    'required' => $mapping->is_required,
                    'page_id' => $page->id,
                    'page_url' => $page->url_pattern,
                    'section' => $mapping->section,
                    'tab_order' => $mapping->tab_order,
                ];

                // Use the primary selector as the key
                $key = $mapping->web_field_name ?? $mapping->web_field_id ?? $mapping->primary_selector;
                $fields[$key] = $fieldData;

                $mappings[] = [
                    'mapping_id' => $mapping->id,
                    'local_field' => $mapping->local_field,
                    'web_field' => $key,
                    'value' => $value,
                    'source' => $mapping->source_description,
                ];

                // Track unmapped required fields
                if ($mapping->is_required && $value === null) {
                    $unmappedRequired[] = [
                        'field' => $mapping->web_field_label,
                        'local_field' => $mapping->local_field,
                    ];
                }
            }
        }

        return [
            'fields' => $fields,
            'mappings' => $mappings,
            'unmapped_required' => $unmappedRequired,
            'total_fields' => count($fields),
            'filled_fields' => count(array_filter($fields, fn($f) => $f['value'] !== null)),
        ];
    }

    /**
     * Map data for a specific page only
     */
    public function mapDeclarationToPage(DeclarationForm $declaration, $page): array
    {
        $declaration->load([
            'invoice.invoiceItems',
            'shipment.shipperContact',
            'shipment.consigneeContact',
            'shipperContact',
            'consigneeContact',
            'country',
        ]);

        $fields = [];

        foreach ($page->activeFieldMappings as $mapping) {
            $value = $mapping->getValueFromDeclaration($declaration);

            $key = $mapping->web_field_name ?? $mapping->web_field_id ?? $mapping->primary_selector;
            $fields[$key] = [
                'name' => $mapping->web_field_label,
                'selectors' => $mapping->getSelectorsForPlaywright(),
                'type' => $mapping->field_type,
                'value' => $value,
                'required' => $mapping->is_required,
            ];
        }

        return $fields;
    }

    /**
     * Preview mapping without actually submitting
     * Shows what values would be sent for each field
     */
    public function previewMapping(DeclarationForm $declaration, WebFormTarget $target): array
    {
        $mapped = $this->mapDeclarationToTarget($declaration, $target);

        $preview = [];
        foreach ($target->pages as $page) {
            $pagePreview = [
                'page_name' => $page->name,
                'page_type' => $page->page_type,
                'url' => $page->full_url,
                'fields' => [],
            ];

            foreach ($page->activeFieldMappings as $mapping) {
                $value = $mapping->getValueFromDeclaration($declaration);

                $pagePreview['fields'][] = [
                    'label' => $mapping->web_field_label,
                    'type' => $mapping->field_type_label,
                    'value' => $value,
                    'source' => $mapping->source_description,
                    'required' => $mapping->is_required,
                    'has_value' => $value !== null,
                    'section' => $mapping->section,
                ];
            }

            $preview[] = $pagePreview;
        }

        return [
            'target_name' => $target->name,
            'target_url' => $target->base_url,
            'pages' => $preview,
            'summary' => [
                'total_fields' => $mapped['total_fields'],
                'filled_fields' => $mapped['filled_fields'],
                'unmapped_required' => $mapped['unmapped_required'],
                'ready_to_submit' => empty($mapped['unmapped_required']),
            ],
        ];
    }

    /**
     * Validate that all required fields have values
     */
    public function validateMapping(DeclarationForm $declaration, WebFormTarget $target): array
    {
        $mapped = $this->mapDeclarationToTarget($declaration, $target);
        
        $errors = [];
        
        foreach ($mapped['unmapped_required'] as $field) {
            $errors[] = "Required field '{$field['field']}' has no value (source: {$field['local_field']})";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => [], // Could add warnings for optional fields
        ];
    }

    /**
     * Build a simple flat array of field => value for Playwright
     * Used for simpler submission scripts
     */
    public function buildSimpleFieldMap(DeclarationForm $declaration, WebFormTarget $target): array
    {
        $mapped = $this->mapDeclarationToTarget($declaration, $target);
        
        $simple = [];
        foreach ($mapped['fields'] as $key => $field) {
            if ($field['value'] !== null) {
                $simple[$key] = $field['value'];
            }
        }

        return $simple;
    }
}
