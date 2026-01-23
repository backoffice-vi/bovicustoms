<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestrictedGood extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'name',
        'description',
        'restriction_type',
        'permit_authority',
        'requirements',
        'detection_keywords',
    ];

    protected $casts = [
        'detection_keywords' => 'array',
    ];

    /**
     * Restriction types
     */
    const TYPE_PERMIT = 'permit';
    const TYPE_LICENSE = 'license';
    const TYPE_QUOTA = 'quota';
    const TYPE_INSPECTION = 'inspection';
    const TYPE_OTHER = 'other';

    /**
     * Get all available restriction types
     */
    public static function getRestrictionTypes(): array
    {
        return [
            self::TYPE_PERMIT => 'Permit Required',
            self::TYPE_LICENSE => 'License Required',
            self::TYPE_QUOTA => 'Quota Restriction',
            self::TYPE_INSPECTION => 'Inspection Required',
            self::TYPE_OTHER => 'Other Restriction',
        ];
    }

    /**
     * Get the country this restricted good belongs to
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Scope to filter by country
     */
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Check if text matches any detection keywords
     */
    public function matchesText(string $text): bool
    {
        if (empty($this->detection_keywords)) {
            return false;
        }

        $textLower = strtolower($text);
        
        foreach ($this->detection_keywords as $keyword) {
            if (str_contains($textLower, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find all restricted goods that match a given text
     */
    public static function findMatching(string $text, int $countryId): \Illuminate\Database\Eloquent\Collection
    {
        return static::forCountry($countryId)
            ->get()
            ->filter(function ($item) use ($text) {
                return $item->matchesText($text);
            });
    }

    /**
     * Get human-readable restriction type name
     */
    public function getRestrictionTypeNameAttribute(): string
    {
        return self::getRestrictionTypes()[$this->restriction_type] ?? $this->restriction_type;
    }

    /**
     * Get full requirements description
     */
    public function getFullRequirementsAttribute(): string
    {
        $parts = [];
        
        if ($this->restriction_type) {
            $parts[] = $this->restriction_type_name;
        }
        
        if ($this->permit_authority) {
            $parts[] = "Authority: {$this->permit_authority}";
        }
        
        if ($this->requirements) {
            $parts[] = $this->requirements;
        }
        
        return implode(' - ', $parts);
    }
}
