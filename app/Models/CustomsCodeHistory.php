<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomsCodeHistory extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'customs_code_history';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    protected $fillable = [
        'customs_code_id',
        'field_changed',
        'old_value',
        'new_value',
        'law_document_id',
        'changed_by',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the customs code this history entry belongs to
     */
    public function customsCode()
    {
        return $this->belongsTo(CustomsCode::class);
    }

    /**
     * Get the law document that triggered this change
     */
    public function lawDocument()
    {
        return $this->belongsTo(LawDocument::class);
    }

    /**
     * Get the user who made this change
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Create a history entry for a field change
     */
    public static function logChange(
        int $customsCodeId,
        string $field,
        ?string $oldValue,
        ?string $newValue,
        ?int $lawDocumentId = null,
        ?int $changedBy = null
    ): self {
        return self::create([
            'customs_code_id' => $customsCodeId,
            'field_changed' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'law_document_id' => $lawDocumentId,
            'changed_by' => $changedBy ?? auth()->id(),
            'created_at' => now(),
        ]);
    }

    /**
     * Scope to filter by customs code
     */
    public function scopeForCode($query, $customsCodeId)
    {
        return $query->where('customs_code_id', $customsCodeId);
    }

    /**
     * Scope to filter by law document
     */
    public function scopeFromDocument($query, $lawDocumentId)
    {
        return $query->where('law_document_id', $lawDocumentId);
    }

    /**
     * Get a formatted description of the change
     */
    public function getChangeDescriptionAttribute(): string
    {
        if ($this->old_value === null) {
            return "Set {$this->field_changed} to \"{$this->new_value}\"";
        }
        
        if ($this->new_value === null) {
            return "Removed {$this->field_changed} (was \"{$this->old_value}\")";
        }
        
        return "Changed {$this->field_changed} from \"{$this->old_value}\" to \"{$this->new_value}\"";
    }
}
