<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebFormFieldMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'web_form_page_id',
        'local_field',
        'local_table',
        'local_column',
        'local_relation',
        'web_field_label',
        'web_field_name',
        'web_field_id',
        'web_field_selectors',
        'field_type',
        'country_reference_type', // Links to country_reference_data table
        'options',
        'value_transform',
        'default_value',
        'static_value',
        'is_required',
        'max_length',
        'validation_pattern',
        'tab_order',
        'section',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'web_field_selectors' => 'array',
        'options' => 'array',
        'value_transform' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function page()
    {
        return $this->belongsTo(WebFormPage::class, 'web_form_page_id');
    }

    public function dropdownValues()
    {
        return $this->hasMany(WebFormDropdownValue::class)->orderBy('sort_order');
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeForSection($query, string $section)
    {
        return $query->where('section', $section);
    }

    // ==========================================
    // Accessors
    // ==========================================

    /**
     * Get field type label
     */
    public function getFieldTypeLabelAttribute(): string
    {
        return match ($this->field_type) {
            'text' => 'Text Input',
            'select' => 'Dropdown',
            'checkbox' => 'Checkbox',
            'radio' => 'Radio Button',
            'date' => 'Date',
            'textarea' => 'Text Area',
            'hidden' => 'Hidden',
            'number' => 'Number',
            default => ucfirst($this->field_type),
        };
    }

    /**
     * Get the primary selector (first one)
     */
    public function getPrimarySelectorAttribute(): ?string
    {
        $selectors = $this->web_field_selectors;
        return $selectors[0] ?? null;
    }

    /**
     * Check if this field has a local data source
     */
    public function getHasLocalSourceAttribute(): bool
    {
        return !empty($this->local_field) || !empty($this->local_table);
    }

    /**
     * Get a human-readable source description
     */
    public function getSourceDescriptionAttribute(): string
    {
        if ($this->static_value) {
            return "Static: {$this->static_value}";
        }

        if ($this->local_field) {
            return $this->local_field;
        }

        if ($this->local_table && $this->local_column) {
            $source = "{$this->local_table}.{$this->local_column}";
            if ($this->local_relation) {
                $source = "{$this->local_relation}.{$this->local_column}";
            }
            return $source;
        }

        if ($this->default_value) {
            return "Default: {$this->default_value}";
        }

        return 'Manual Entry';
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Get value from a declaration form using this mapping
     */
    public function getValueFromDeclaration(DeclarationForm $declaration): mixed
    {
        // Static value takes precedence
        if ($this->static_value !== null) {
            return $this->transformValue($this->static_value);
        }

        $value = null;

        // Try to get value from local_field path (e.g., "declaration.vessel_name" or "shipper.company_name")
        if ($this->local_field) {
            $value = $this->resolveFieldPath($declaration, $this->local_field);
        }

        // Try explicit table/column/relation
        if ($value === null && $this->local_table) {
            $value = $this->resolveTableColumn($declaration);
        }

        // Use default if still no value
        if ($value === null && $this->default_value !== null) {
            $value = $this->default_value;
        }

        // Apply transformation
        if ($value !== null) {
            $value = $this->transformValue($value);
        }

        // For dropdowns, map to the option value
        if ($value !== null && $this->field_type === 'select') {
            $value = $this->mapToDropdownValue($value);
        }

        return $value;
    }

    /**
     * Resolve a dot-notation field path
     */
    protected function resolveFieldPath(DeclarationForm $declaration, string $path): mixed
    {
        $parts = explode('.', $path);
        $source = $parts[0];
        $field = $parts[1] ?? null;

        if (!$field) {
            return null;
        }

        switch ($source) {
            case 'declaration':
                return $declaration->{$field};

            case 'shipment':
                return $declaration->shipment?->{$field};

            case 'invoice':
                return $declaration->invoice?->{$field};

            case 'shipper':
                $shipper = $declaration->shipperContact ?? $declaration->shipment?->shipperContact;
                return $shipper?->{$field};

            case 'consignee':
                $consignee = $declaration->consigneeContact ?? $declaration->shipment?->consigneeContact;
                return $consignee?->{$field};

            case 'country':
                return $declaration->country?->{$field};

            default:
                return null;
        }
    }

    /**
     * Resolve table/column reference
     */
    protected function resolveTableColumn(DeclarationForm $declaration): mixed
    {
        $model = null;

        switch ($this->local_table) {
            case 'declaration_forms':
                $model = $declaration;
                break;
            case 'shipments':
                $model = $declaration->shipment;
                break;
            case 'invoices':
                $model = $declaration->invoice;
                break;
            case 'trade_contacts':
                if ($this->local_relation === 'shipperContact') {
                    $model = $declaration->shipperContact ?? $declaration->shipment?->shipperContact;
                } elseif ($this->local_relation === 'consigneeContact') {
                    $model = $declaration->consigneeContact ?? $declaration->shipment?->consigneeContact;
                }
                break;
            case 'countries':
                $model = $declaration->country;
                break;
        }

        if ($model && $this->local_column) {
            return $model->{$this->local_column};
        }

        return null;
    }

    /**
     * Apply value transformation
     */
    protected function transformValue(mixed $value): mixed
    {
        if ($value === null || !$this->value_transform) {
            return $value;
        }

        $transform = $this->value_transform;
        $type = $transform['type'] ?? null;

        switch ($type) {
            case 'date_format':
                if ($value instanceof \Carbon\Carbon) {
                    return $value->format($transform['format'] ?? 'Y-m-d');
                }
                if (is_string($value)) {
                    return date($transform['format'] ?? 'Y-m-d', strtotime($value));
                }
                break;

            case 'uppercase':
                return strtoupper((string) $value);

            case 'lowercase':
                return strtolower((string) $value);

            case 'number_format':
                return number_format(
                    (float) $value,
                    $transform['decimals'] ?? 2,
                    $transform['decimal_separator'] ?? '.',
                    $transform['thousands_separator'] ?? ''
                );

            case 'prefix':
                return ($transform['prefix'] ?? '') . $value;

            case 'suffix':
                return $value . ($transform['suffix'] ?? '');

            case 'map':
                $mappings = $transform['mappings'] ?? [];
                return $mappings[$value] ?? $value;

            case 'truncate':
                $length = $transform['length'] ?? 255;
                return substr((string) $value, 0, $length);
        }

        return $value;
    }

    /**
     * Map a local value to the corresponding dropdown option value
     */
    protected function mapToDropdownValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $stringValue = (string) $value;

        // First, check country reference data if this mapping has a reference type set
        if ($this->country_reference_type) {
            $countryId = $this->page?->target?->country_id;
            if ($countryId) {
                $refMatch = CountryReferenceData::findByLocalMatch(
                    $countryId,
                    $this->country_reference_type,
                    $stringValue
                );
                if ($refMatch) {
                    return $refMatch->code;
                }
            }
        }

        // Fallback to dropdown values for form-specific overrides
        $match = $this->dropdownValues()
            ->where(function ($query) use ($stringValue) {
                $query->where('local_equivalent', $stringValue)
                    ->orWhereJsonContains('local_matches', $stringValue)
                    ->orWhere('option_value', $stringValue)
                    ->orWhere('option_label', $stringValue);
            })
            ->first();

        if ($match) {
            return $match->option_value;
        }

        // Check static options
        if ($this->options) {
            foreach ($this->options as $option) {
                if (($option['local'] ?? null) === $stringValue ||
                    ($option['value'] ?? null) === $stringValue ||
                    ($option['label'] ?? null) === $stringValue) {
                    return $option['value'];
                }
            }
        }

        // Return original if no mapping found
        return $value;
    }

    /**
     * Get dropdown options - prefers country reference data, falls back to form-specific values
     */
    public function getDropdownOptionsForForm(): array
    {
        // If this mapping uses country reference data, get from there
        if ($this->country_reference_type) {
            $countryId = $this->page?->target?->country_id;
            if ($countryId) {
                return CountryReferenceData::getDropdownOptions($countryId, $this->country_reference_type);
            }
        }

        // Fall back to form-specific dropdown values
        return $this->dropdownValues()
            ->get()
            ->map(fn($v) => [
                'value' => $v->option_value,
                'label' => $v->option_label,
                'is_default' => $v->is_default,
            ])
            ->toArray();
    }

    /**
     * Get all selectors as an array for Playwright
     */
    public function getSelectorsForPlaywright(): array
    {
        $selectors = $this->web_field_selectors ?? [];

        // Add fallbacks based on name and id
        if ($this->web_field_name) {
            $selectors[] = "[name=\"{$this->web_field_name}\"]";
        }
        if ($this->web_field_id) {
            $selectors[] = "#{$this->web_field_id}";
        }

        return array_unique($selectors);
    }
}
