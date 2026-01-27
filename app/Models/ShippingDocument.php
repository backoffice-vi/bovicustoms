<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ShippingDocument extends Model
{
    use HasFactory;

    /**
     * Document type constants
     */
    const TYPE_BILL_OF_LADING = 'bill_of_lading';
    const TYPE_AIR_WAYBILL = 'air_waybill';
    const TYPE_PACKING_LIST = 'packing_list';
    const TYPE_CERTIFICATE_OF_ORIGIN = 'certificate_of_origin';
    const TYPE_INSURANCE_CERTIFICATE = 'insurance_certificate';
    const TYPE_COMMERCIAL_INVOICE = 'commercial_invoice';
    const TYPE_OTHER = 'other';

    /**
     * Extraction status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'shipment_id',
        'organization_id',
        'user_id',
        'document_type',
        'document_number',
        'manifest_number',
        'container_id',
        'filename',
        'original_filename',
        'file_path',
        'file_type',
        'file_size',
        'extraction_status',
        'extracted_at',
        'extraction_error',
        'carrier_name',
        'vessel_name',
        'voyage_number',
        'port_of_loading',
        'port_of_discharge',
        'final_destination',
        'shipping_date',
        'estimated_arrival',
        'shipper_details',
        'consignee_details',
        'notify_party_details',
        'forwarding_agent_details',
        'freight_charges',
        'freight_terms',
        'insurance_amount',
        'other_charges',
        'currency',
        'total_packages',
        'package_type',
        'goods_description',
        'gross_weight_kg',
        'net_weight_kg',
        'volume_cbm',
        'invoice_references',
        'extracted_data',
        'extracted_text',
        'extraction_meta',
        'is_verified',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'shipper_details' => 'array',
        'consignee_details' => 'array',
        'notify_party_details' => 'array',
        'forwarding_agent_details' => 'array',
        'invoice_references' => 'array',
        'extracted_data' => 'array',
        'extraction_meta' => 'array',
        'freight_charges' => 'decimal:2',
        'insurance_amount' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'gross_weight_kg' => 'decimal:3',
        'net_weight_kg' => 'decimal:3',
        'volume_cbm' => 'decimal:3',
        'total_packages' => 'integer',
        'file_size' => 'integer',
        'is_verified' => 'boolean',
        'shipping_date' => 'date',
        'estimated_arrival' => 'date',
        'extracted_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Boot method to apply tenant scope
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('tenant', function ($builder) {
            if (auth()->check()) {
                $user = auth()->user();
                if ($user->organization_id) {
                    $builder->where('organization_id', $user->organization_id);
                } elseif ($user->is_individual) {
                    $builder->where('user_id', $user->id);
                }
            }
        });
    }

    /**
     * Get all document types with labels
     */
    public static function getDocumentTypes(): array
    {
        return [
            self::TYPE_BILL_OF_LADING => 'Bill of Lading',
            self::TYPE_AIR_WAYBILL => 'Air Waybill (AWB)',
            self::TYPE_PACKING_LIST => 'Packing List',
            self::TYPE_CERTIFICATE_OF_ORIGIN => 'Certificate of Origin',
            self::TYPE_INSURANCE_CERTIFICATE => 'Insurance Certificate',
            self::TYPE_COMMERCIAL_INVOICE => 'Commercial Invoice',
            self::TYPE_OTHER => 'Other Document',
        ];
    }

    /**
     * Get all extraction statuses with labels
     */
    public static function getExtractionStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
        ];
    }

    // ==========================================
    // Relationships
    // ==========================================

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ==========================================
    // Accessors
    // ==========================================

    public function getDocumentTypeLabelAttribute(): string
    {
        return self::getDocumentTypes()[$this->document_type] ?? 'Unknown';
    }

    public function getExtractionStatusLabelAttribute(): string
    {
        return self::getExtractionStatuses()[$this->extraction_status] ?? 'Unknown';
    }

    public function getExtractionStatusColorAttribute(): string
    {
        return match ($this->extraction_status) {
            self::STATUS_PENDING => 'secondary',
            self::STATUS_PROCESSING => 'warning',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'danger',
            default => 'secondary',
        };
    }

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' bytes';
    }

    public function getFileUrlAttribute(): ?string
    {
        if ($this->file_path) {
            return Storage::url($this->file_path);
        }
        return null;
    }

    public function getDisplayNumberAttribute(): string
    {
        return $this->document_number ?? $this->manifest_number ?? "Doc #{$this->id}";
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeBillsOfLading($query)
    {
        return $query->where('document_type', self::TYPE_BILL_OF_LADING);
    }

    public function scopeAirWaybills($query)
    {
        return $query->where('document_type', self::TYPE_AIR_WAYBILL);
    }

    public function scopePending($query)
    {
        return $query->where('extraction_status', self::STATUS_PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('extraction_status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('extraction_status', self::STATUS_FAILED);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    // ==========================================
    // Status Methods
    // ==========================================

    public function isPending(): bool
    {
        return $this->extraction_status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->extraction_status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->extraction_status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->extraction_status === self::STATUS_FAILED;
    }

    public function markAsProcessing(): void
    {
        $this->update(['extraction_status' => self::STATUS_PROCESSING]);
    }

    public function markAsCompleted(array $extractedData = []): void
    {
        $this->update([
            'extraction_status' => self::STATUS_COMPLETED,
            'extracted_at' => now(),
            'extraction_error' => null,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'extraction_status' => self::STATUS_FAILED,
            'extraction_error' => $error,
        ]);
    }

    public function verify(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Update shipment with data from this document
     */
    public function syncToShipment(): void
    {
        $this->shipment->updateFromShippingDocument($this);
    }

    /**
     * Get shipper name from extracted details
     */
    public function getShipperName(): ?string
    {
        return $this->shipper_details['company_name'] 
            ?? $this->shipper_details['name'] 
            ?? null;
    }

    /**
     * Get consignee name from extracted details
     */
    public function getConsigneeName(): ?string
    {
        return $this->consignee_details['company_name'] 
            ?? $this->consignee_details['name'] 
            ?? null;
    }

    /**
     * Get notify party name from extracted details
     */
    public function getNotifyPartyName(): ?string
    {
        return $this->notify_party_details['company_name'] 
            ?? $this->notify_party_details['name'] 
            ?? null;
    }

    /**
     * Check if this is a primary transport document (B/L or AWB)
     */
    public function isPrimaryTransportDocument(): bool
    {
        return in_array($this->document_type, [
            self::TYPE_BILL_OF_LADING,
            self::TYPE_AIR_WAYBILL,
        ]);
    }

    /**
     * Delete the file from storage
     */
    public function deleteFile(): bool
    {
        if ($this->file_path && Storage::exists($this->file_path)) {
            return Storage::delete($this->file_path);
        }
        return false;
    }

    /**
     * Get file contents
     */
    public function getFileContents(): ?string
    {
        if ($this->file_path && Storage::exists($this->file_path)) {
            return Storage::get($this->file_path);
        }
        return null;
    }

    /**
     * Get full file path for processing
     */
    public function getFullFilePath(): ?string
    {
        if ($this->file_path) {
            return Storage::path($this->file_path);
        }
        return null;
    }
}
