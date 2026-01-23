<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TariffSectionNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'tariff_section_id',
        'note_number',
        'note_type',
        'note_text',
    ];

    /**
     * Note types
     */
    const TYPE_GENERAL = 'general';
    const TYPE_EXCLUSION = 'exclusion';
    const TYPE_DEFINITION = 'definition';
    const TYPE_RULE = 'rule';

    /**
     * Get the section this note belongs to
     */
    public function section()
    {
        return $this->belongsTo(TariffSection::class, 'tariff_section_id');
    }

    /**
     * Scope for exclusion notes
     */
    public function scopeExclusions($query)
    {
        return $query->where('note_type', self::TYPE_EXCLUSION);
    }

    /**
     * Scope for definition notes
     */
    public function scopeDefinitions($query)
    {
        return $query->where('note_type', self::TYPE_DEFINITION);
    }

    /**
     * Check if this is an exclusion note
     */
    public function isExclusion(): bool
    {
        return $this->note_type === self::TYPE_EXCLUSION;
    }

    /**
     * Get formatted reference (e.g., "Section XVI, Note 1")
     */
    public function getFormattedReferenceAttribute(): string
    {
        $section = $this->section;
        $noteNum = $this->note_number ? ", Note {$this->note_number}" : "";
        return "{$section->formatted_identifier}{$noteNum}";
    }
}
