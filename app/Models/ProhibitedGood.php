<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProhibitedGood extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'name',
        'description',
        'legal_reference',
        'detection_keywords',
    ];

    protected $casts = [
        'detection_keywords' => 'array',
    ];

    /**
     * Get the country this prohibited good belongs to
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
     * Find all prohibited goods that match a given text
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
     * Check if any prohibited good matches the text
     */
    public static function isProhibited(string $text, int $countryId): bool
    {
        return static::findMatching($text, $countryId)->isNotEmpty();
    }
}
