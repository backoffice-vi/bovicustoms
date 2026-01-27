<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebFormPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'web_form_target_id',
        'name',
        'url_pattern',
        'sequence_order',
        'page_type',
        'page_snapshot',
        'navigation',
        'submit_selector',
        'success_indicator',
        'error_indicator',
        'is_active',
    ];

    protected $casts = [
        'page_snapshot' => 'array',
        'navigation' => 'array',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function target()
    {
        return $this->belongsTo(WebFormTarget::class, 'web_form_target_id');
    }

    public function fieldMappings()
    {
        return $this->hasMany(WebFormFieldMapping::class)->orderBy('tab_order');
    }

    public function activeFieldMappings()
    {
        return $this->hasMany(WebFormFieldMapping::class)
            ->where('is_active', true)
            ->orderBy('tab_order');
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('page_type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sequence_order');
    }

    // ==========================================
    // Accessors
    // ==========================================

    /**
     * Get full URL for this page
     */
    public function getFullUrlAttribute(): string
    {
        return rtrim($this->target->base_url, '/') . '/' . ltrim($this->url_pattern, '/');
    }

    /**
     * Get the page type label
     */
    public function getPageTypeLabelAttribute(): string
    {
        return match ($this->page_type) {
            'login' => 'Login Page',
            'form' => 'Form Page',
            'confirmation' => 'Confirmation Page',
            'search' => 'Search Page',
            'other' => 'Other',
            default => ucfirst($this->page_type),
        };
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Get field mappings grouped by section
     */
    public function getFieldMappingsBySection(): array
    {
        $mappings = $this->activeFieldMappings;
        $grouped = [];

        foreach ($mappings as $mapping) {
            $section = $mapping->section ?? 'General';
            if (!isset($grouped[$section])) {
                $grouped[$section] = [];
            }
            $grouped[$section][] = $mapping;
        }

        return $grouped;
    }

    /**
     * Check if page has required fields that are not mapped
     */
    public function hasUnmappedRequiredFields(): bool
    {
        return $this->fieldMappings()
            ->where('is_required', true)
            ->whereNull('local_field')
            ->whereNull('static_value')
            ->whereNull('default_value')
            ->exists();
    }
}
