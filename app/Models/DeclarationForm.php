<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeclarationForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'country_id',
        'invoice_id',
        'shipment_id',
        'shipper_contact_id',
        'consignee_contact_id',
        'form_number',
        'declaration_date',
        'total_duty',
        'fob_value',
        'freight_total',
        'insurance_total',
        'cif_value',
        'freight_prorated',
        'insurance_prorated',
        'customs_duty_total',
        'wharfage_total',
        'other_levies_total',
        'duty_breakdown',
        'levy_breakdown',
        'manifest_number',
        'bill_of_lading_number',
        'awb_number',
        'carrier_name',
        'vessel_name',
        'port_of_loading',
        'port_of_arrival',
        'arrival_date',
        'total_packages',
        'package_type',
        'gross_weight_kg',
        'net_weight_kg',
        'country_of_origin',
        'cpc_code',
        'currency',
        'exchange_rate',
        'items',
        'source_type',
        'source_file_path',
        'extracted_text',
        'extraction_meta',
    ];

    protected $casts = [
        'declaration_date' => 'date',
        'arrival_date' => 'date',
        'total_duty' => 'decimal:2',
        'fob_value' => 'decimal:2',
        'freight_total' => 'decimal:2',
        'insurance_total' => 'decimal:2',
        'cif_value' => 'decimal:2',
        'customs_duty_total' => 'decimal:2',
        'wharfage_total' => 'decimal:2',
        'other_levies_total' => 'decimal:2',
        'gross_weight_kg' => 'decimal:3',
        'net_weight_kg' => 'decimal:3',
        'exchange_rate' => 'decimal:6',
        'total_packages' => 'integer',
        'freight_prorated' => 'boolean',
        'insurance_prorated' => 'boolean',
        'items' => 'array',
        'duty_breakdown' => 'array',
        'levy_breakdown' => 'array',
        'extraction_meta' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        
        // Apply global scope to filter by tenant
        static::addGlobalScope('tenant', function ($builder) {
            if (auth()->check()) {
                $user = auth()->user();
                if ($user->organization_id) {
                    $builder->where('organization_id', $user->organization_id);
                } else if ($user->is_individual) {
                    $builder->whereHas('invoice', function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    });
                }
            }
        });
    }

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * Get the organization this form belongs to
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the country for this form
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the invoice this form is for
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the shipment this form is for
     */
    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * Get the shipper contact
     */
    public function shipperContact()
    {
        return $this->belongsTo(TradeContact::class, 'shipper_contact_id');
    }

    /**
     * Get the consignee contact
     */
    public function consigneeContact()
    {
        return $this->belongsTo(TradeContact::class, 'consignee_contact_id');
    }

    /**
     * Get declaration items
     */
    public function declarationItems()
    {
        return $this->hasMany(DeclarationFormItem::class);
    }

    // ==========================================
    // Accessors
    // ==========================================

    /**
     * Get the primary document number (B/L or AWB)
     */
    public function getPrimaryDocumentNumberAttribute(): ?string
    {
        return $this->bill_of_lading_number ?? $this->awb_number ?? $this->manifest_number;
    }

    /**
     * Get total charges (FOB + Freight + Insurance)
     */
    public function getTotalChargesAttribute(): float
    {
        return ($this->fob_value ?? 0) + ($this->freight_total ?? 0) + ($this->insurance_total ?? 0);
    }

    /**
     * Get formatted FOB value
     */
    public function getFormattedFobAttribute(): string
    {
        $currency = $this->currency ?? 'USD';
        return $currency . ' ' . number_format($this->fob_value ?? 0, 2);
    }

    /**
     * Get formatted CIF value
     */
    public function getFormattedCifAttribute(): string
    {
        $currency = $this->currency ?? 'USD';
        return $currency . ' ' . number_format($this->cif_value ?? 0, 2);
    }

    /**
     * Get formatted total duty
     */
    public function getFormattedTotalDutyAttribute(): string
    {
        $currency = $this->currency ?? 'USD';
        return $currency . ' ' . number_format($this->total_duty ?? 0, 2);
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Calculate CIF value from components
     */
    public function calculateCif(): float
    {
        return ($this->fob_value ?? 0) + ($this->freight_total ?? 0) + ($this->insurance_total ?? 0);
    }

    /**
     * Calculate total duty from breakdowns
     */
    public function calculateTotalDuty(): float
    {
        return ($this->customs_duty_total ?? 0) + ($this->wharfage_total ?? 0) + ($this->other_levies_total ?? 0);
    }

    /**
     * Recalculate and save totals
     */
    public function recalculateTotals(): void
    {
        $this->cif_value = $this->calculateCif();
        $this->total_duty = $this->calculateTotalDuty();
        $this->save();
    }

    /**
     * Populate from shipment
     */
    public function populateFromShipment(Shipment $shipment): void
    {
        $this->shipment_id = $shipment->id;
        $this->shipper_contact_id = $shipment->shipper_contact_id;
        $this->consignee_contact_id = $shipment->consignee_contact_id;
        $this->fob_value = $shipment->fob_total;
        $this->freight_total = $shipment->freight_total;
        $this->insurance_total = $shipment->insurance_total;
        $this->cif_value = $shipment->cif_total;
        $this->freight_prorated = $shipment->invoices()->count() > 1;
        $this->insurance_prorated = $shipment->invoices()->count() > 1;
        $this->manifest_number = $shipment->manifest_number;
        $this->bill_of_lading_number = $shipment->bill_of_lading_number;
        $this->awb_number = $shipment->awb_number;
        $this->carrier_name = $shipment->carrier_name;
        $this->vessel_name = $shipment->vessel_name;
        $this->port_of_loading = $shipment->port_of_loading;
        $this->port_of_arrival = $shipment->port_of_discharge;
        $this->arrival_date = $shipment->actual_arrival_date ?? $shipment->estimated_arrival_date;
        $this->total_packages = $shipment->total_packages;
        $this->package_type = $shipment->package_type;
        $this->gross_weight_kg = $shipment->gross_weight_kg;
        $this->net_weight_kg = $shipment->net_weight_kg;
    }

    /**
     * Get all invoices associated with this declaration
     * (either through shipment or direct invoice link)
     */
    public function getAllInvoices(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->shipment_id) {
            return $this->shipment->invoices;
        }

        if ($this->invoice_id) {
            return collect([$this->invoice]);
        }

        return collect();
    }

    /**
     * Get all line items from all invoices
     */
    public function getAllLineItems(): \Illuminate\Support\Collection
    {
        $items = collect();

        foreach ($this->getAllInvoices() as $invoice) {
            if ($invoice->invoiceItems) {
                $items = $items->merge($invoice->invoiceItems);
            }
        }

        return $items;
    }
}
