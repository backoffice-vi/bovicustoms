<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebFormDropdownValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'web_form_field_mapping_id',
        'option_value',
        'option_label',
        'local_equivalent',
        'local_matches',
        'sort_order',
        'is_default',
    ];

    protected $casts = [
        'local_matches' => 'array',
        'is_default' => 'boolean',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function fieldMapping()
    {
        return $this->belongsTo(WebFormFieldMapping::class, 'web_form_field_mapping_id');
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Check if this option matches a local value
     */
    public function matchesLocalValue(string $localValue): bool
    {
        // Direct match
        if ($this->local_equivalent === $localValue) {
            return true;
        }

        // Check in local_matches array
        if ($this->local_matches && in_array($localValue, $this->local_matches)) {
            return true;
        }

        // Case-insensitive match on value or label
        $lowerLocal = strtolower($localValue);
        if (strtolower($this->option_value) === $lowerLocal ||
            strtolower($this->option_label) === $lowerLocal) {
            return true;
        }

        return false;
    }

    /**
     * Add a local match value
     */
    public function addLocalMatch(string $value): void
    {
        $matches = $this->local_matches ?? [];
        if (!in_array($value, $matches)) {
            $matches[] = $value;
            $this->update(['local_matches' => $matches]);
        }
    }
}
