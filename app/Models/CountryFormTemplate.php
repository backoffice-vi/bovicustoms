<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CountryFormTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'name',
        'description',
        'filename',
        'original_filename',
        'file_path',
        'file_type',
        'file_size',
        'form_type',
        'is_active',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Form type constants
     */
    const FORM_TYPE_CUSTOMS_DECLARATION = 'customs_declaration';
    const FORM_TYPE_IMPORT_PERMIT = 'import_permit';
    const FORM_TYPE_EXPORT_PERMIT = 'export_permit';
    const FORM_TYPE_CERTIFICATE_OF_ORIGIN = 'certificate_of_origin';
    const FORM_TYPE_BILL_OF_LADING = 'bill_of_lading';
    const FORM_TYPE_COMMERCIAL_INVOICE = 'commercial_invoice';
    const FORM_TYPE_PACKING_LIST = 'packing_list';
    const FORM_TYPE_OTHER = 'other';

    /**
     * Get all form types with labels
     */
    public static function getFormTypes(): array
    {
        return [
            self::FORM_TYPE_CUSTOMS_DECLARATION => 'Customs Declaration',
            self::FORM_TYPE_IMPORT_PERMIT => 'Import Permit',
            self::FORM_TYPE_EXPORT_PERMIT => 'Export Permit',
            self::FORM_TYPE_CERTIFICATE_OF_ORIGIN => 'Certificate of Origin',
            self::FORM_TYPE_BILL_OF_LADING => 'Bill of Lading',
            self::FORM_TYPE_COMMERCIAL_INVOICE => 'Commercial Invoice',
            self::FORM_TYPE_PACKING_LIST => 'Packing List',
            self::FORM_TYPE_OTHER => 'Other',
        ];
    }

    /**
     * Get the country this template belongs to
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the user who uploaded this template
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope to filter by active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by form type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('form_type', $type);
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
     * Get human-readable form type
     */
    public function getFormTypeLabelAttribute(): string
    {
        return self::getFormTypes()[$this->form_type] ?? 'Unknown';
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
}
