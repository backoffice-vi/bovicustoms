<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SubmissionAttachment extends Model
{
    use HasFactory;

    /**
     * Document type constants
     */
    const TYPE_DECLARATION_FORM = 'declaration_form';
    const TYPE_COMMERCIAL_INVOICE = 'commercial_invoice';
    const TYPE_BILL_OF_LADING = 'bill_of_lading';
    const TYPE_AIR_WAYBILL = 'air_waybill';
    const TYPE_CERTIFICATE_OF_ORIGIN = 'certificate_of_origin';
    const TYPE_PACKING_LIST = 'packing_list';
    const TYPE_INSURANCE_CERTIFICATE = 'insurance_certificate';
    const TYPE_IMPORT_PERMIT = 'import_permit';
    const TYPE_OTHER = 'other';

    protected $fillable = [
        'declaration_form_id',
        'uploaded_by_user_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'document_type',
        'description',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    /**
     * Get all document types with labels
     */
    public static function getDocumentTypes(): array
    {
        return [
            self::TYPE_DECLARATION_FORM => 'Declaration Form',
            self::TYPE_COMMERCIAL_INVOICE => 'Commercial Invoice',
            self::TYPE_BILL_OF_LADING => 'Bill of Lading',
            self::TYPE_AIR_WAYBILL => 'Air Waybill',
            self::TYPE_CERTIFICATE_OF_ORIGIN => 'Certificate of Origin',
            self::TYPE_PACKING_LIST => 'Packing List',
            self::TYPE_INSURANCE_CERTIFICATE => 'Insurance Certificate',
            self::TYPE_IMPORT_PERMIT => 'Import Permit',
            self::TYPE_OTHER => 'Other',
        ];
    }

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * Get the declaration form this attachment belongs to
     */
    public function declarationForm()
    {
        return $this->belongsTo(DeclarationForm::class);
    }

    /**
     * Get the user who uploaded this attachment
     */
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    // ==========================================
    // Accessors
    // ==========================================

    /**
     * Get document type label
     */
    public function getDocumentTypeLabelAttribute(): string
    {
        return self::getDocumentTypes()[$this->document_type] ?? 'Unknown';
    }

    /**
     * Get human-readable file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Get file extension
     */
    public function getFileExtensionAttribute(): string
    {
        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
    }

    /**
     * Get icon class based on file type
     */
    public function getFileIconAttribute(): string
    {
        $extension = $this->file_extension;
        
        return match ($extension) {
            'pdf' => 'fas fa-file-pdf text-danger',
            'doc', 'docx' => 'fas fa-file-word text-primary',
            'xls', 'xlsx' => 'fas fa-file-excel text-success',
            'jpg', 'jpeg', 'png', 'gif' => 'fas fa-file-image text-info',
            default => 'fas fa-file text-secondary',
        };
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Get the full storage path
     */
    public function getFullPath(): string
    {
        return Storage::disk('local')->path($this->file_path);
    }

    /**
     * Check if file exists in storage
     */
    public function fileExists(): bool
    {
        return Storage::disk('local')->exists($this->file_path);
    }

    /**
     * Get file contents
     */
    public function getFileContents(): ?string
    {
        if (!$this->fileExists()) {
            return null;
        }
        
        return Storage::disk('local')->get($this->file_path);
    }

    /**
     * Delete the file from storage
     */
    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return Storage::disk('local')->delete($this->file_path);
        }
        
        return true;
    }

    /**
     * Delete the model and its file
     */
    public function deleteWithFile(): bool
    {
        $this->deleteFile();
        return $this->delete();
    }

    /**
     * Get the storage directory for a declaration
     */
    public static function getStorageDirectory(int $declarationFormId): string
    {
        return "submissions/{$declarationFormId}";
    }

    /**
     * Store a file for a declaration
     */
    public static function storeFile(
        DeclarationForm $declaration,
        User $user,
        \Illuminate\Http\UploadedFile $file,
        string $documentType = self::TYPE_OTHER,
        ?string $description = null
    ): self {
        $directory = self::getStorageDirectory($declaration->id);
        $fileName = $file->getClientOriginalName();
        $path = $file->store($directory, 'local');

        return self::create([
            'declaration_form_id' => $declaration->id,
            'uploaded_by_user_id' => $user->id,
            'file_name' => $fileName,
            'file_path' => $path,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'document_type' => $documentType,
            'description' => $description,
        ]);
    }

    /**
     * Get allowed file extensions
     */
    public static function getAllowedExtensions(): array
    {
        return ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
    }

    /**
     * Get max file size in bytes (10MB)
     */
    public static function getMaxFileSize(): int
    {
        return 10 * 1024 * 1024; // 10MB
    }
}
