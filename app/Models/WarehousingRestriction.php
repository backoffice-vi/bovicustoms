<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehousingRestriction extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'name',
        'description',
        'legal_reference',
        'exceptions',
        'detection_keywords',
        'is_active',
    ];

    protected $casts = [
        'exceptions' => 'array',
        'detection_keywords' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the country this restriction belongs to
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Scope for active restrictions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
     * Check if an exception applies
     */
    public function hasException(string $text): bool
    {
        if (empty($this->exceptions)) {
            return false;
        }

        $textLower = strtolower($text);
        
        foreach ($this->exceptions as $exception) {
            if (str_contains($textLower, strtolower($exception))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find all warehousing restrictions that match a given text
     */
    public static function findMatching(string $text, int $countryId): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->forCountry($countryId)
            ->get()
            ->filter(function ($item) use ($text) {
                return $item->matchesText($text) && !$item->hasException($text);
            });
    }

    /**
     * Check if goods cannot be warehoused
     */
    public static function isRestricted(string $text, int $countryId): bool
    {
        return static::findMatching($text, $countryId)->isNotEmpty();
    }
}
