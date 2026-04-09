<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapsImport extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_DOWNLOADING = 'downloading';
    const STATUS_DOWNLOADED = 'downloaded';
    const STATUS_IMPORTING = 'importing';
    const STATUS_IMPORTED = 'imported';
    const STATUS_PROCESSING_INVOICES = 'processing_invoices';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'organization_id',
        'country_id',
        'td_number',
        'status',
        'caps_data',
        'attachments',
        'items_count',
        'error_message',
        'ai_diagnosis',
        'retry_count',
        'shipment_id',
        'declaration_form_id',
        'download_path',
        'downloaded_at',
        'imported_at',
        'invoices_processed_at',
    ];

    protected $casts = [
        'caps_data' => 'array',
        'attachments' => 'array',
        'ai_diagnosis' => 'array',
        'items_count' => 'integer',
        'retry_count' => 'integer',
        'downloaded_at' => 'datetime',
        'imported_at' => 'datetime',
        'invoices_processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function declarationForm(): BelongsTo
    {
        return $this->belongsTo(DeclarationForm::class);
    }

    public function markAs(string $status, ?string $error = null): self
    {
        $this->status = $status;
        if ($error) {
            $this->error_message = $error;
        }
        if ($status === self::STATUS_DOWNLOADED) {
            $this->downloaded_at = now();
        } elseif ($status === self::STATUS_IMPORTED) {
            $this->imported_at = now();
        } elseif ($status === self::STATUS_COMPLETED) {
            $this->invoices_processed_at = now();
        }
        $this->save();
        return $this;
    }

    public function storeAiDiagnosis(array $recovery): self
    {
        $existing = $this->ai_diagnosis ?? [];

        $existing['diagnosis'] = $recovery['diagnosis'] ?? null;
        $existing['recommendations'] = $recovery['recommendations'] ?? [];
        $existing['error_categories'] = $recovery['error_categories'] ?? [];
        $existing['can_retry'] = $recovery['can_retry'] ?? false;
        $existing['analyzed_at'] = now()->toIso8601String();

        $fixes = $recovery['fixes_applied'] ?? [];
        if (!empty($fixes)) {
            $existing['auto_fixes_applied'] = array_merge(
                $existing['auto_fixes_applied'] ?? [],
                $fixes
            );
        }

        $this->ai_diagnosis = $existing;
        $this->save();
        return $this;
    }

    public function getAiDiagnosisText(): ?string
    {
        return $this->ai_diagnosis['diagnosis'] ?? null;
    }

    public function getAiRecommendations(): array
    {
        return $this->ai_diagnosis['recommendations'] ?? [];
    }

    public function getErrorCategories(): array
    {
        return $this->ai_diagnosis['error_categories'] ?? [];
    }

    public function getAutoFixesApplied(): array
    {
        return $this->ai_diagnosis['auto_fixes_applied'] ?? [];
    }

    public function getAttachmentInvoices(): array
    {
        $attachments = $this->attachments ?? [];
        return array_filter($attachments, function ($file) {
            $lower = strtolower($file);
            return !str_contains($lower, 'bol') && !str_contains($lower, 'bolrel')
                && str_ends_with($lower, '.pdf');
        });
    }

    public function getDownloadFullPath(): ?string
    {
        if (!$this->download_path) {
            return null;
        }
        return storage_path('app/caps-downloads/' . $this->td_number);
    }

    public function hasInvoiceAttachments(): bool
    {
        return count($this->getAttachmentInvoices()) > 0;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_DOWNLOADING => 'Downloading',
            self::STATUS_DOWNLOADED => 'Downloaded',
            self::STATUS_IMPORTING => 'Importing',
            self::STATUS_IMPORTED => 'Imported',
            self::STATUS_PROCESSING_INVOICES => 'Processing Invoices',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            default => ucfirst($status),
        };
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'secondary',
            self::STATUS_DOWNLOADING, self::STATUS_IMPORTING, self::STATUS_PROCESSING_INVOICES => 'info',
            self::STATUS_DOWNLOADED, self::STATUS_IMPORTED => 'primary',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
            default => 'secondary',
        };
    }
}
