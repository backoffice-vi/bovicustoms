<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TariffChapter extends Model
{
    use HasFactory;

    protected $fillable = [
        'tariff_section_id',
        'chapter_number',
        'title',
        'description',
        'country_id',
    ];

    /**
     * Get the section this chapter belongs to
     */
    public function section()
    {
        return $this->belongsTo(TariffSection::class, 'tariff_section_id');
    }

    /**
     * Get the country this chapter belongs to
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the notes for this chapter
     */
    public function notes()
    {
        return $this->hasMany(TariffChapterNote::class)->orderBy('note_number');
    }

    /**
     * Get the customs codes in this chapter
     */
    public function customsCodes()
    {
        return $this->hasMany(CustomsCode::class, 'tariff_chapter_id');
    }

    /**
     * Get exclusion rules where this chapter is the source
     */
    public function exclusionRulesAsSource()
    {
        return $this->hasMany(ClassificationExclusion::class, 'source_chapter_id');
    }

    /**
     * Get exclusion rules where this chapter is the target
     */
    public function exclusionRulesAsTarget()
    {
        return $this->hasMany(ClassificationExclusion::class, 'target_chapter_id');
    }

    /**
     * Get additional levies for this chapter
     */
    public function additionalLevies()
    {
        return $this->hasMany(AdditionalLevy::class);
    }

    /**
     * Scope to filter by country
     */
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Get all exclusion notes for this chapter
     */
    public function exclusionNotes()
    {
        return $this->notes()->where('note_type', 'exclusion');
    }

    /**
     * Get all notes including parent section notes
     */
    public function getAllApplicableNotes(): array
    {
        $notes = [];
        
        // Add section notes first
        if ($this->section) {
            foreach ($this->section->notes as $note) {
                $notes[] = [
                    'type' => 'section',
                    'reference' => $note->formatted_reference,
                    'note_type' => $note->note_type,
                    'text' => $note->note_text,
                ];
            }
        }
        
        // Add chapter notes
        foreach ($this->notes as $note) {
            $notes[] = [
                'type' => 'chapter',
                'reference' => $note->formatted_reference,
                'note_type' => $note->note_type,
                'text' => $note->note_text,
            ];
        }
        
        return $notes;
    }

    /**
     * Get formatted chapter identifier (e.g., "Chapter 84")
     */
    public function getFormattedIdentifierAttribute(): string
    {
        return "Chapter {$this->chapter_number}";
    }
}
