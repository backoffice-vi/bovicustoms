<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebFormSubmission extends Model
{
    use HasFactory;

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_FAILED = 'failed';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'web_form_target_id',
        'declaration_form_id',
        'user_id',
        'organization_id',
        'status',
        'mapped_data',
        'submission_log',
        'ai_decisions',
        'screenshots',
        'external_reference',
        'external_response',
        'error_message',
        'errors_encountered',
        'retry_count',
        'started_at',
        'completed_at',
        'duration_seconds',
    ];

    protected $casts = [
        'mapped_data' => 'array',
        'submission_log' => 'array',
        'ai_decisions' => 'array',
        'screenshots' => 'array',
        'errors_encountered' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function target()
    {
        return $this->belongsTo(WebFormTarget::class, 'web_form_target_id');
    }

    public function declaration()
    {
        return $this->belongsTo(DeclarationForm::class, 'declaration_form_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_CONFIRMED]);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ==========================================
    // Accessors
    // ==========================================

    /**
     * Get all status options with labels
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? 'Unknown';
    }

    /**
     * Get status color for UI badges
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'secondary',
            self::STATUS_IN_PROGRESS => 'info',
            self::STATUS_SUBMITTED => 'primary',
            self::STATUS_FAILED => 'danger',
            self::STATUS_CONFIRMED => 'success',
            self::STATUS_REJECTED => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Check if submission was successful
     */
    public function getIsSuccessfulAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_CONFIRMED]);
    }

    /**
     * Check if submission can be retried
     */
    public function getCanRetryAttribute(): bool
    {
        return $this->status === self::STATUS_FAILED && $this->retry_count < 3;
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration_seconds) {
            return '-';
        }

        if ($this->duration_seconds < 60) {
            return "{$this->duration_seconds}s";
        }

        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;
        return "{$minutes}m {$seconds}s";
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Start the submission
     */
    public function start(): void
    {
        $this->update([
            'status' => self::STATUS_IN_PROGRESS,
            'started_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark as submitted successfully
     */
    public function markSubmitted(string $reference = null, string $response = null): void
    {
        $this->update([
            'status' => self::STATUS_SUBMITTED,
            'external_reference' => $reference,
            'external_response' => $response,
            'completed_at' => now(),
            'duration_seconds' => $this->started_at ? now()->diffInSeconds($this->started_at) : null,
        ]);
    }

    /**
     * Mark as confirmed
     */
    public function markConfirmed(): void
    {
        $this->update([
            'status' => self::STATUS_CONFIRMED,
        ]);
    }

    /**
     * Mark as failed
     */
    public function markFailed(string $errorMessage, array $errors = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'errors_encountered' => $errors,
            'completed_at' => now(),
            'duration_seconds' => $this->started_at ? now()->diffInSeconds($this->started_at) : null,
        ]);
    }

    /**
     * Mark as rejected
     */
    public function markRejected(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'external_response' => $reason,
        ]);
    }

    /**
     * Add a log entry
     */
    public function addLog(string $message, string $level = 'info'): void
    {
        $logs = $this->submission_log ?? [];
        $logs[] = [
            'timestamp' => now()->toIso8601String(),
            'level' => $level,
            'message' => $message,
        ];
        $this->update(['submission_log' => $logs]);
    }

    /**
     * Add an AI decision entry
     */
    public function addAiDecision(string $situation, string $decision, string $reasoning): void
    {
        $decisions = $this->ai_decisions ?? [];
        $decisions[] = [
            'timestamp' => now()->toIso8601String(),
            'situation' => $situation,
            'decision' => $decision,
            'reasoning' => $reasoning,
        ];
        $this->update(['ai_decisions' => $decisions]);
    }

    /**
     * Add screenshot path
     */
    public function addScreenshot(string $path): void
    {
        $screenshots = $this->screenshots ?? [];
        $screenshots[] = $path;
        $this->update(['screenshots' => $screenshots]);
    }

    /**
     * Increment retry count
     */
    public function incrementRetry(): void
    {
        $this->increment('retry_count');
    }

    /**
     * Store mapped data
     */
    public function setMappedData(array $data): void
    {
        $this->update(['mapped_data' => $data]);
    }

    /**
     * Create a retry submission
     */
    public function createRetry(): self
    {
        return self::create([
            'web_form_target_id' => $this->web_form_target_id,
            'declaration_form_id' => $this->declaration_form_id,
            'user_id' => auth()->id() ?? $this->user_id,
            'organization_id' => $this->organization_id,
            'status' => self::STATUS_PENDING,
            'retry_count' => $this->retry_count + 1,
        ]);
    }
}
