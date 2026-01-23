<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    /**
     * Status constants
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_DOCUMENTS_UPLOADED = 'documents_uploaded';
    const STATUS_DECLARATION_GENERATED = 'declaration_generated';
    const STATUS_RELEASED = 'released';

    /**
     * Insurance method constants
     */
    const INSURANCE_MANUAL = 'manual';
    const INSURANCE_PERCENTAGE = 'percentage';
    const INSURANCE_DOCUMENT = 'document';

    protected $fillable = [
        'organization_id',
        'user_id',
        'country_id',
        'status',
        'source_type',
        'shipper_contact_id',
        'consignee_contact_id',
        'notify_party_contact_id',
        'fob_total',
        'freight_total',
        'insurance_total',
        'cif_total',
        'insurance_method',
        'insurance_percentage',
        'bill_of_lading_number',
        'awb_number',
        'manifest_number',
        'carrier_name',
        'vessel_name',
        'voyage_number',
        'port_of_loading',
        'port_of_discharge',
        'final_destination',
        'estimated_arrival_date',
        'actual_arrival_date',
        'total_packages',
        'package_type',
        'gross_weight_kg',
        'net_weight_kg',
        'notes',
    ];

    protected $casts = [
        'fob_total' => 'decimal:2',
        'freight_total' => 'decimal:2',
        'insurance_total' => 'decimal:2',
        'cif_total' => 'decimal:2',
        'insurance_percentage' => 'decimal:2',
        'gross_weight_kg' => 'decimal:3',
        'net_weight_kg' => 'decimal:3',
        'estimated_arrival_date' => 'date',
        'actual_arrival_date' => 'date',
        'total_packages' => 'integer',
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
     * Get all status options
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_DOCUMENTS_UPLOADED => 'Documents Uploaded',
            self::STATUS_DECLARATION_GENERATED => 'Declaration Generated',
            self::STATUS_RELEASED => 'Released',
        ];
    }

    /**
     * Get all insurance method options
     */
    public static function getInsuranceMethods(): array
    {
        return [
            self::INSURANCE_MANUAL => 'Manual Entry',
            self::INSURANCE_PERCENTAGE => 'Percentage of FOB',
            self::INSURANCE_DOCUMENT => 'From Insurance Certificate',
        ];
    }

    // ==========================================
    // Relationships
    // ==========================================

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function shipperContact()
    {
        return $this->belongsTo(TradeContact::class, 'shipper_contact_id');
    }

    public function consigneeContact()
    {
        return $this->belongsTo(TradeContact::class, 'consignee_contact_id');
    }

    public function notifyPartyContact()
    {
        return $this->belongsTo(TradeContact::class, 'notify_party_contact_id');
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'shipment_invoices')
            ->withPivot(['prorated_freight', 'prorated_insurance', 'invoice_fob'])
            ->withTimestamps();
    }

    public function shippingDocuments()
    {
        return $this->hasMany(ShippingDocument::class);
    }

    public function declarationForms()
    {
        return $this->hasMany(DeclarationForm::class);
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeWithDocuments($query)
    {
        return $query->where('status', '!=', self::STATUS_DRAFT);
    }

    // ==========================================
    // Accessors
    // ==========================================

    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? 'Unknown';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'secondary',
            self::STATUS_DOCUMENTS_UPLOADED => 'info',
            self::STATUS_DECLARATION_GENERATED => 'primary',
            self::STATUS_RELEASED => 'success',
            default => 'secondary',
        };
    }

    public function getInsuranceMethodLabelAttribute(): string
    {
        return self::getInsuranceMethods()[$this->insurance_method] ?? 'Manual Entry';
    }

    public function getInvoiceCountAttribute(): int
    {
        return $this->invoices()->count();
    }

    public function getDocumentCountAttribute(): int
    {
        return $this->shippingDocuments()->count();
    }

    public function getPrimaryDocumentNumberAttribute(): ?string
    {
        return $this->bill_of_lading_number ?? $this->awb_number ?? $this->manifest_number;
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Calculate FOB total from all invoices
     */
    public function calculateFobTotal(): float
    {
        return (float) $this->invoices->sum('total_amount');
    }

    /**
     * Calculate CIF total
     */
    public function calculateCifTotal(): float
    {
        return $this->fob_total + $this->freight_total + $this->insurance_total;
    }

    /**
     * Calculate insurance based on method
     */
    public function calculateInsurance(): float
    {
        if ($this->insurance_method === self::INSURANCE_PERCENTAGE && $this->insurance_percentage) {
            return $this->fob_total * ($this->insurance_percentage / 100);
        }

        return (float) $this->insurance_total;
    }

    /**
     * Recalculate all totals and proration
     */
    public function recalculateTotals(): void
    {
        $this->fob_total = $this->calculateFobTotal();
        
        if ($this->insurance_method === self::INSURANCE_PERCENTAGE && $this->insurance_percentage) {
            $this->insurance_total = $this->calculateInsurance();
        }
        
        $this->cif_total = $this->calculateCifTotal();
        
        $this->save();
        
        // Prorate freight and insurance across invoices
        $this->prorateToInvoices();
    }

    /**
     * Prorate freight and insurance across invoices based on FOB ratio
     */
    public function prorateToInvoices(): void
    {
        if ($this->fob_total <= 0) {
            return;
        }

        foreach ($this->invoices as $invoice) {
            $invoiceFob = (float) $invoice->total_amount;
            $ratio = $invoiceFob / $this->fob_total;

            $this->invoices()->updateExistingPivot($invoice->id, [
                'invoice_fob' => $invoiceFob,
                'prorated_freight' => round($this->freight_total * $ratio, 2),
                'prorated_insurance' => round($this->insurance_total * $ratio, 2),
            ]);
        }
    }

    /**
     * Add an invoice to this shipment
     */
    public function addInvoice(Invoice $invoice): void
    {
        if (!$this->invoices()->where('invoice_id', $invoice->id)->exists()) {
            $this->invoices()->attach($invoice->id, [
                'invoice_fob' => $invoice->total_amount,
            ]);
            $this->recalculateTotals();
        }
    }

    /**
     * Remove an invoice from this shipment
     */
    public function removeInvoice(Invoice $invoice): void
    {
        $this->invoices()->detach($invoice->id);
        $this->recalculateTotals();
    }

    /**
     * Update status
     */
    public function updateStatus(string $status): void
    {
        $this->update(['status' => $status]);
    }

    /**
     * Check if shipment can generate declaration
     */
    public function canGenerateDeclaration(): bool
    {
        return $this->invoices()->count() > 0 
            && $this->fob_total > 0
            && in_array($this->status, [self::STATUS_DRAFT, self::STATUS_DOCUMENTS_UPLOADED]);
    }

    /**
     * Get Bill of Lading document if exists
     */
    public function getBillOfLading(): ?ShippingDocument
    {
        return $this->shippingDocuments()
            ->where('document_type', ShippingDocument::TYPE_BILL_OF_LADING)
            ->first();
    }

    /**
     * Update shipping details from a document
     */
    public function updateFromShippingDocument(ShippingDocument $document): void
    {
        $updates = [];

        if ($document->document_type === ShippingDocument::TYPE_BILL_OF_LADING) {
            if ($document->document_number) {
                $updates['bill_of_lading_number'] = $document->document_number;
            }
        } elseif ($document->document_type === ShippingDocument::TYPE_AIR_WAYBILL) {
            if ($document->document_number) {
                $updates['awb_number'] = $document->document_number;
            }
        }

        if ($document->manifest_number) {
            $updates['manifest_number'] = $document->manifest_number;
        }
        if ($document->carrier_name) {
            $updates['carrier_name'] = $document->carrier_name;
        }
        if ($document->vessel_name) {
            $updates['vessel_name'] = $document->vessel_name;
        }
        if ($document->voyage_number) {
            $updates['voyage_number'] = $document->voyage_number;
        }
        if ($document->port_of_loading) {
            $updates['port_of_loading'] = $document->port_of_loading;
        }
        if ($document->port_of_discharge) {
            $updates['port_of_discharge'] = $document->port_of_discharge;
        }
        if ($document->final_destination) {
            $updates['final_destination'] = $document->final_destination;
        }
        if ($document->freight_charges) {
            $updates['freight_total'] = $document->freight_charges;
        }
        if ($document->total_packages) {
            $updates['total_packages'] = $document->total_packages;
        }
        if ($document->gross_weight_kg) {
            $updates['gross_weight_kg'] = $document->gross_weight_kg;
        }

        if (!empty($updates)) {
            $this->update($updates);
            $this->recalculateTotals();
        }
    }
}
