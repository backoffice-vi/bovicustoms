<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TariffSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_number',
        'title',
        'description',
        'country_id',
    ];

    /**
     * Get the country this section belongs to
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the notes for this section
     */
    public function notes()
    {
        return $this->hasMany(TariffSectionNote::class)->orderBy('note_number');
    }

    /**
     * Get the chapters in this section
     */
    public function chapters()
    {
        return $this->hasMany(TariffChapter::class)->orderBy('chapter_number');
    }

    /**
     * Scope to filter by country
     */
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Get all exclusion notes for this section
     */
    public function exclusionNotes()
    {
        return $this->notes()->where('note_type', 'exclusion');
    }

    /**
     * Get formatted section identifier (e.g., "Section XVI")
     */
    public function getFormattedIdentifierAttribute(): string
    {
        return "Section {$this->section_number}";
    }
}
