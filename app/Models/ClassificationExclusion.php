<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassificationExclusion extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'source_chapter_id',
        'exclusion_pattern',
        'target_chapter_id',
        'target_heading',
        'rule_text',
        'source_note_reference',
        'priority',
    ];

    protected $casts = [
        'priority' => 'integer',
    ];

    /**
     * Get the country this exclusion belongs to
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the source chapter (the chapter that excludes)
     */
    public function sourceChapter()
    {
        return $this->belongsTo(TariffChapter::class, 'source_chapter_id');
    }

    /**
     * Get the target chapter (where to redirect)
     */
    public function targetChapter()
    {
        return $this->belongsTo(TariffChapter::class, 'target_chapter_id');
    }

    /**
     * Check if a text matches this exclusion pattern
     */
    public function matchesText(string $text): bool
    {
        $pattern = $this->exclusion_pattern;
        
        // Convert wildcard pattern to regex
        $regex = '/\b' . str_replace(
            ['*', '?'],
            ['.*', '.'],
            preg_quote($pattern, '/')
        ) . '/i';
        
        return preg_match($regex, $text) === 1;
    }

    /**
     * Scope to filter by country
     */
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Scope to filter by source chapter
     */
    public function scopeForSourceChapter($query, $chapterId)
    {
        return $query->where('source_chapter_id', $chapterId);
    }

    /**
     * Get all exclusions that might match a given text for a country
     */
    public static function findMatchingExclusions(string $text, int $countryId, ?int $sourceChapterId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::forCountry($countryId)->orderBy('priority', 'desc');
        
        if ($sourceChapterId) {
            $query->forSourceChapter($sourceChapterId);
        }
        
        return $query->get()->filter(function ($exclusion) use ($text) {
            return $exclusion->matchesText($text);
        });
    }

    /**
     * Get formatted rule description
     */
    public function getFormattedRuleAttribute(): string
    {
        $source = $this->sourceChapter?->formatted_identifier ?? 'Unknown';
        $target = $this->targetChapter?->formatted_identifier ?? $this->target_heading ?? 'Unknown';
        
        return "{$source}: {$this->exclusion_pattern} → {$target}";
    }
}
