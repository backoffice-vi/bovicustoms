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

    /**
     * CAPS response status constants (distinct from FTP-layer `status`).
     */
    const CAPS_RESPONSE_PENDING = 'pending';
    /** Broker has uploaded a CAPS report; we're waiting on the parser job. */
    const CAPS_RESPONSE_PARSING = 'parsing';
    const CAPS_RESPONSE_ACCEPTED = 'accepted';
    const CAPS_RESPONSE_REJECTED = 'rejected';
    const CAPS_RESPONSE_PARTIAL = 'partial';
    /** Parser job failed; we still have the uploaded file but no errors yet. */
    const CAPS_RESPONSE_PARSE_FAILED = 'parse_failed';

    /**
     * CAPS response source constants.
     */
    const CAPS_SOURCE_MANUAL_UPLOAD = 'manual_upload';
    const CAPS_SOURCE_FTP_POLL = 'ftp_poll';
    const CAPS_SOURCE_EMAIL = 'email';

    /**
     * Submission type constants
     */
    const TYPE_WEB = 'web';
    const TYPE_FTP = 'ftp';

    protected $fillable = [
        'web_form_target_id',
        'declaration_form_id',
        'user_id',
        'organization_id',
        'submission_type',
        'status',
        'is_successful',
        'mapped_data',
        'request_data',
        'response_data',
        'submission_log',
        'ai_decisions',
        'screenshots',
        'external_reference',
        'external_response',
        'error_message',
        'errors_encountered',
        'retry_count',
        'started_at',
        'submitted_at',
        'completed_at',
        'duration_seconds',
        'caps_response_status',
        'caps_response_received_at',
        'caps_response_source',
        'caps_response_file_path',
        'caps_response_errors',
        'parent_submission_id',
    ];

    protected $casts = [
        'mapped_data' => 'array',
        'request_data' => 'array',
        'response_data' => 'array',
        'submission_log' => 'array',
        'ai_decisions' => 'array',
        'screenshots' => 'array',
        'errors_encountered' => 'array',
        'is_successful' => 'boolean',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'caps_response_received_at' => 'datetime',
        'caps_response_errors' => 'array',
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

    /**
     * FTP attachments uploaded for this submission (B/L, invoices, etc).
     */
    public function ftpAttachments()
    {
        return $this->hasMany(FtpSubmissionAttachment::class, 'web_form_submission_id');
    }

    /**
     * Original submission this one was created from (for resubmissions/amendments).
     */
    public function parentSubmission()
    {
        return $this->belongsTo(self::class, 'parent_submission_id');
    }

    /**
     * Resubmissions/amendments derived from this submission.
     */
    public function childSubmissions()
    {
        return $this->hasMany(self::class, 'parent_submission_id');
    }

    /**
     * Walk to the root of the resubmission chain.
     */
    public function rootSubmission(): self
    {
        $cursor = $this;
        while ($cursor->parent_submission_id) {
            $next = $cursor->parentSubmission()->first();
            if (!$next) {
                break;
            }
            $cursor = $next;
        }
        return $cursor;
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

    public function scopeWeb($query)
    {
        return $query->where('submission_type', self::TYPE_WEB);
    }

    public function scopeFtp($query)
    {
        return $query->where('submission_type', self::TYPE_FTP);
    }

    public function scopeForDeclaration($query, $declarationId)
    {
        return $query->where('declaration_form_id', $declarationId);
    }

    public function scopeCapsAccepted($query)
    {
        return $query->where('caps_response_status', self::CAPS_RESPONSE_ACCEPTED);
    }

    public function scopeCapsRejected($query)
    {
        return $query->whereIn('caps_response_status', [
            self::CAPS_RESPONSE_REJECTED,
            self::CAPS_RESPONSE_PARTIAL,
        ]);
    }

    public function scopeAwaitingCapsResponse($query)
    {
        return $query->where('caps_response_status', self::CAPS_RESPONSE_PENDING);
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
     * Uses stored value if set, otherwise derives from status
     */
    public function getIsSuccessfulAttribute(): bool
    {
        if (isset($this->attributes['is_successful'])) {
            return (bool) $this->attributes['is_successful'];
        }
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_CONFIRMED]);
    }

    /**
     * Check if this is an FTP submission
     */
    public function getIsFtpAttribute(): bool
    {
        return $this->submission_type === self::TYPE_FTP;
    }

    /**
     * Check if this is a web submission
     */
    public function getIsWebAttribute(): bool
    {
        return $this->submission_type === self::TYPE_WEB;
    }

    /**
     * Get submission type label
     */
    public function getSubmissionTypeLabelAttribute(): string
    {
        return match ($this->submission_type) {
            self::TYPE_WEB => 'Web Portal',
            self::TYPE_FTP => 'FTP Upload',
            default => 'Unknown',
        };
    }

    /**
     * Check if submission can be retried.
     *
     * A submission is retryable if either:
     *  - the FTP-layer submission failed (legacy retry path), or
     *  - CAPS rejected the declaration after a successful FTP upload.
     *
     * Retries are capped at 3 to avoid runaway loops.
     */
    public function getCanRetryAttribute(): bool
    {
        if ($this->retry_count >= 3) {
            return false;
        }

        if ($this->status === self::STATUS_FAILED) {
            return true;
        }

        return in_array($this->caps_response_status, [
            self::CAPS_RESPONSE_REJECTED,
            self::CAPS_RESPONSE_PARTIAL,
        ], true);
    }

    /**
     * True if CAPS has issued any response (accepted, rejected, partial).
     * Excludes the interim parsing/parse_failed states — those mean we
     * received a file from the broker but haven't successfully parsed it yet.
     */
    public function getHasCapsResponseAttribute(): bool
    {
        return in_array($this->caps_response_status, [
            self::CAPS_RESPONSE_ACCEPTED,
            self::CAPS_RESPONSE_REJECTED,
            self::CAPS_RESPONSE_PARTIAL,
        ], true);
    }

    /**
     * True if CAPS accepted the declaration. Amendments are only allowed
     * against an accepted parent.
     */
    public function getCapsAcceptedAttribute(): bool
    {
        return $this->caps_response_status === self::CAPS_RESPONSE_ACCEPTED;
    }

    /**
     * True if CAPS rejected the declaration (full or partial).
     */
    public function getCapsRejectedAttribute(): bool
    {
        return in_array($this->caps_response_status, [
            self::CAPS_RESPONSE_REJECTED,
            self::CAPS_RESPONSE_PARTIAL,
        ], true);
    }

    /**
     * Get CAPS response status label.
     */
    public function getCapsResponseLabelAttribute(): string
    {
        return match ($this->caps_response_status) {
            self::CAPS_RESPONSE_ACCEPTED => 'Accepted by CAPS',
            self::CAPS_RESPONSE_REJECTED => 'Rejected by CAPS',
            self::CAPS_RESPONSE_PARTIAL => 'Partially accepted',
            self::CAPS_RESPONSE_PENDING => 'Awaiting CAPS response',
            self::CAPS_RESPONSE_PARSING => 'Parsing CAPS report…',
            self::CAPS_RESPONSE_PARSE_FAILED => 'Could not parse CAPS report',
            default => 'Unknown',
        };
    }

    /**
     * Get CAPS response status color for UI badges.
     */
    public function getCapsResponseColorAttribute(): string
    {
        return match ($this->caps_response_status) {
            self::CAPS_RESPONSE_ACCEPTED => 'success',
            self::CAPS_RESPONSE_REJECTED => 'danger',
            self::CAPS_RESPONSE_PARTIAL => 'warning',
            self::CAPS_RESPONSE_PENDING => 'secondary',
            self::CAPS_RESPONSE_PARSING => 'info',
            self::CAPS_RESPONSE_PARSE_FAILED => 'warning',
            default => 'secondary',
        };
    }

    /**
     * IANA timezone used for displaying this submission's timestamps.
     * Resolved from the declaration's country, falling back to
     * Country::DEFAULT_TIMEZONE (America/Tortola) when the declaration or
     * country isn't loadable. Database storage stays UTC.
     */
    public function getDisplayTimezoneAttribute(): string
    {
        $country = $this->declaration?->country;

        if ($country instanceof Country) {
            return $country->getEffectiveTimezone();
        }

        return Country::DEFAULT_TIMEZONE;
    }

    /**
     * Format any timestamp on this submission in the resolved local timezone.
     * Useful for Blade: {{ $submission->formatLocalTime($submission->submitted_at) }}.
     */
    public function formatLocalTime($value, string $format = 'd/m/Y H:i:s'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $tz = $this->display_timezone;

        if ($value instanceof \Carbon\CarbonInterface) {
            $dt = $value->copy();
        } elseif ($value instanceof \DateTimeInterface) {
            $dt = \Illuminate\Support\Carbon::instance($value);
        } else {
            $dt = \Illuminate\Support\Carbon::parse($value);
        }

        return $dt->setTimezone($tz)->format($format);
    }

    /**
     * Short local-timezone abbreviation for display (e.g. AST).
     */
    public function getLocalTimezoneAbbreviationAttribute(): string
    {
        return now()->setTimezone($this->display_timezone)->format('T');
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
     * Mark this submission as having a CAPS report queued for parsing.
     * The result page renders an interim "parsing" state with auto-refresh
     * while the background job runs CapsResponseParser on the file.
     */
    public function markCapsParsing(
        string $source = self::CAPS_SOURCE_MANUAL_UPLOAD,
        ?string $filePath = null
    ): void {
        $this->update([
            'caps_response_status' => self::CAPS_RESPONSE_PARSING,
            'caps_response_received_at' => now(),
            'caps_response_source' => $source,
            'caps_response_file_path' => $filePath,
            'caps_response_errors' => null,
        ]);
    }

    /**
     * Mark a CAPS report as failed to parse. We keep the uploaded file path
     * so the broker can re-trigger parsing or fall back to manual review.
     */
    public function markCapsParseFailed(string $reason): void
    {
        $this->update([
            'caps_response_status' => self::CAPS_RESPONSE_PARSE_FAILED,
            'caps_response_errors' => [
                'parse_error' => $reason,
            ],
        ]);
    }

    /**
     * True while the parser job is still running on a freshly uploaded report.
     */
    public function getIsCapsParsingAttribute(): bool
    {
        return $this->caps_response_status === self::CAPS_RESPONSE_PARSING;
    }

    /**
     * True if the parser failed on the uploaded report.
     */
    public function getCapsParseFailedAttribute(): bool
    {
        return $this->caps_response_status === self::CAPS_RESPONSE_PARSE_FAILED;
    }

    /**
     * Record a CAPS rejection (full or partial). Does not change the FTP-layer
     * `status`, which remains `submitted` because the file was uploaded
     * successfully — only CAPS's verdict on the contents changed.
     */
    public function markCapsRejected(
        array $errors,
        string $source = self::CAPS_SOURCE_MANUAL_UPLOAD,
        ?string $filePath = null,
        bool $partial = false
    ): void {
        $this->update([
            'caps_response_status' => $partial
                ? self::CAPS_RESPONSE_PARTIAL
                : self::CAPS_RESPONSE_REJECTED,
            'caps_response_received_at' => now(),
            'caps_response_source' => $source,
            'caps_response_file_path' => $filePath,
            'caps_response_errors' => $errors,
        ]);
    }

    /**
     * Record a CAPS acceptance.
     */
    public function markCapsAccepted(
        string $source = self::CAPS_SOURCE_MANUAL_UPLOAD,
        ?string $filePath = null
    ): void {
        $this->update([
            'caps_response_status' => self::CAPS_RESPONSE_ACCEPTED,
            'caps_response_received_at' => now(),
            'caps_response_source' => $source,
            'caps_response_file_path' => $filePath,
            'caps_response_errors' => null,
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
     * Create a retry submission. The new submission keeps a `parent_submission_id`
     * link back to the original so the resubmission chain is queryable.
     */
    public function createRetry(): self
    {
        return self::create([
            'web_form_target_id' => $this->web_form_target_id,
            'declaration_form_id' => $this->declaration_form_id,
            'user_id' => auth()->id() ?? $this->user_id,
            'organization_id' => $this->organization_id,
            'submission_type' => $this->submission_type,
            'status' => self::STATUS_PENDING,
            'retry_count' => $this->retry_count + 1,
            'parent_submission_id' => $this->id,
        ]);
    }
}
