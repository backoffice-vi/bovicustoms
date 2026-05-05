<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a file attached to a CAPS FTP submission (B/L, invoice, etc).
 *
 * Naming convention (per CAPS Electronic Submission Guide v4.0 §1.9):
 *   <T12 filename>.<A-Z>.<extension>
 * Example: 10018405052026.001.A.pdf
 *
 * Status flow:
 *   pending -> uploaded -> confirmed (or rejected)
 *   pending -> failed
 */
class FtpSubmissionAttachment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'web_form_submission_id',
        'letter',
        'original_filename',
        'remote_filename',
        'local_path',
        'mime_type',
        'size_bytes',
        'source_type',
        'source_reference',
        'status',
        'uploaded_at',
        'response_received_at',
        'error_message',
        'caps_response_text',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'response_received_at' => 'datetime',
        'size_bytes' => 'integer',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(WebFormSubmission::class, 'web_form_submission_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isUploaded(): bool
    {
        return in_array($this->status, [self::STATUS_UPLOADED, self::STATUS_CONFIRMED]);
    }

    public function isAwaitingResponse(): bool
    {
        return $this->status === self::STATUS_UPLOADED;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [
            self::STATUS_CONFIRMED,
            self::STATUS_REJECTED,
            self::STATUS_FAILED,
        ]);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_UPLOADED => 'Uploaded — awaiting CAPS confirmation',
            self::STATUS_CONFIRMED => 'Confirmed by CAPS',
            self::STATUS_REJECTED => 'Rejected by CAPS',
            self::STATUS_FAILED => 'Failed',
            default => ucfirst((string) $this->status),
        };
    }
}
