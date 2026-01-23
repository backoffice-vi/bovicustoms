<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CountrySupportDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'title',
        'description',
        'filename',
        'original_filename',
        'file_path',
        'file_type',
        'file_size',
        'document_type',
        'extracted_text',
        'extracted_at',
        'status',
        'error_message',
        'is_active',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_active' => 'boolean',
        'extracted_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Document type constants
     */
    const DOC_TYPE_TARIFF_SCHEDULE = 'tariff_schedule';
    const DOC_TYPE_REGULATION = 'regulation';
    const DOC_TYPE_GUIDELINE = 'guideline';
    const DOC_TYPE_NOTICE = 'notice';
    const DOC_TYPE_TRADE_AGREEMENT = 'trade_agreement';
    const DOC_TYPE_PROCEDURE_MANUAL = 'procedure_manual';
    const DOC_TYPE_FEE_SCHEDULE = 'fee_schedule';
    const DOC_TYPE_OTHER = 'other';

    /**
     * Get all document types with labels
     */
    public static function getDocumentTypes(): array
    {
        return [
            self::DOC_TYPE_TARIFF_SCHEDULE => 'Tariff Schedule',
            self::DOC_TYPE_REGULATION => 'Regulation',
            self::DOC_TYPE_GUIDELINE => 'Guideline',
            self::DOC_TYPE_NOTICE => 'Notice',
            self::DOC_TYPE_TRADE_AGREEMENT => 'Trade Agreement',
            self::DOC_TYPE_PROCEDURE_MANUAL => 'Procedure Manual',
            self::DOC_TYPE_FEE_SCHEDULE => 'Fee Schedule',
            self::DOC_TYPE_OTHER => 'Other',
        ];
    }

    /**
     * Get the country this document belongs to
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the user who uploaded this document
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope to filter by active documents
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by document type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter completed documents with extracted text
     */
    public function scopeWithExtractedText($query)
    {
        return $query->where('status', self::STATUS_COMPLETED)
                     ->whereNotNull('extracted_text');
    }

    /**
     * Get the full storage path
     */
    public function getFullPath(): string
    {
        return storage_path('app/' . $this->file_path);
    }

    /**
     * Check if file exists
     */
    public function fileExists(): bool
    {
        return Storage::exists($this->file_path);
    }

    /**
     * Check if document is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if document is processing
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if document is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if document processing failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if extracted text is available
     */
    public function hasExtractedText(): bool
    {
        return !empty($this->extracted_text);
    }

    /**
     * Mark document as processing
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'error_message' => null,
        ]);
    }

    /**
     * Mark document as completed with extracted text
     */
    public function markAsCompleted(string $text): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'extracted_text' => $text,
            'extracted_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark document as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
        ]);
    }

    /**
     * Get human-readable file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get human-readable document type
     */
    public function getDocumentTypeLabelAttribute(): string
    {
        return self::getDocumentTypes()[$this->document_type] ?? 'Unknown';
    }

    /**
     * Get file icon class based on file type
     */
    public function getFileIconClassAttribute(): string
    {
        return match($this->file_type) {
            'pdf' => 'fa-file-pdf text-danger',
            'doc', 'docx' => 'fa-file-word text-primary',
            'xls', 'xlsx' => 'fa-file-excel text-success',
            'txt' => 'fa-file-alt text-secondary',
            default => 'fa-file text-muted',
        };
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'bg-warning',
            self::STATUS_PROCESSING => 'bg-info',
            self::STATUS_COMPLETED => 'bg-success',
            self::STATUS_FAILED => 'bg-danger',
            default => 'bg-secondary',
        };
    }
}
