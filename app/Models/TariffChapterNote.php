<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TariffChapterNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'tariff_chapter_id',
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
    const TYPE_SUBHEADING_NOTE = 'subheading_note';

    /**
     * Get the chapter this note belongs to
     */
    public function chapter()
    {
        return $this->belongsTo(TariffChapter::class, 'tariff_chapter_id');
    }

    /**
     * Scope for exclusion notes
     */
    public function scopeExclusions($query)
    {
        return $query->where('note_type', self::TYPE_EXCLUSION);
    }

    /**
     * Scope for subheading notes
     */
    public function scopeSubheadingNotes($query)
    {
        return $query->where('note_type', self::TYPE_SUBHEADING_NOTE);
    }

    /**
     * Check if this is an exclusion note
     */
    public function isExclusion(): bool
    {
        return $this->note_type === self::TYPE_EXCLUSION;
    }

    /**
     * Get formatted reference (e.g., "Chapter 84, Note 1")
     */
    public function getFormattedReferenceAttribute(): string
    {
        $chapter = $this->chapter;
        $noteNum = $this->note_number ? ", Note {$this->note_number}" : "";
        return "{$chapter->formatted_identifier}{$noteNum}";
    }
}
