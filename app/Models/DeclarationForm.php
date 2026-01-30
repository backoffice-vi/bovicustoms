<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeclarationForm extends Model
{
    use HasFactory;

    /**
     * Submission status constants
     */
    const SUBMISSION_STATUS_DRAFT = 'draft';
    const SUBMISSION_STATUS_READY = 'ready';
    const SUBMISSION_STATUS_SUBMITTED = 'submitted';
    const SUBMISSION_STATUS_ACCEPTED = 'accepted';
    const SUBMISSION_STATUS_REJECTED = 'rejected';

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
        // Submission fields
        'submission_status',
        'submitted_by_user_id',
        'submitted_at',
        'submission_reference',
        'submission_notes',
        'submission_response_at',
        'submission_response',
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
        'submitted_at' => 'datetime',
        'submission_response_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        // Apply global scope to filter by tenant (updated to support agents)
        static::addGlobalScope('tenant', function ($builder) {
            if (auth()->check()) {
                $user = auth()->user();
                
                // Admins see everything
                if ($user->isAdmin()) {
                    return;
                }
                
                // Agents see declarations for all their client organizations
                if ($user->isAgent()) {
                    $clientOrgIds = $user->getAgentClientIds();
                    if (!empty($clientOrgIds)) {
                        // Use table-qualified column name to avoid ambiguity in joins
                        $builder->whereIn('declaration_forms.organization_id', $clientOrgIds);
                    } else {
                        // Agent with no clients sees nothing
                        $builder->whereRaw('1 = 0');
                    }
                    return;
                }
                
                // Regular organization users
                if ($user->organization_id) {
                    // Use table-qualified column name to avoid ambiguity in joins
                    $builder->where('declaration_forms.organization_id', $user->organization_id);
                } else if ($user->is_individual) {
                    $builder->whereHas('invoice', function ($query) use ($user) {
                        $query->where('invoices.user_id', $user->id);
                    });
                }
            }
        });
    }

    /**
     * Get all submission statuses with labels
     */
    public static function getSubmissionStatuses(): array
    {
        return [
            self::SUBMISSION_STATUS_DRAFT => 'Draft',
            self::SUBMISSION_STATUS_READY => 'Ready to Submit',
            self::SUBMISSION_STATUS_SUBMITTED => 'Submitted',
            self::SUBMISSION_STATUS_ACCEPTED => 'Accepted',
            self::SUBMISSION_STATUS_REJECTED => 'Rejected',
        ];
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

    /**
     * Get the user who submitted this declaration
     */
    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * Get submission attachments
     */
    public function submissionAttachments()
    {
        return $this->hasMany(SubmissionAttachment::class);
    }

    /**
     * Get the filled declaration forms generated from this declaration
     */
    public function filledForms()
    {
        return $this->hasMany(FilledDeclarationForm::class);
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
        if ($this->shipment_id && $this->shipment) {
            return $this->shipment->invoices ?? collect();
        }

        if ($this->invoice_id && $this->invoice) {
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

    // ==========================================
    // Submission Methods
    // ==========================================

    /**
     * Get submission status label
     */
    public function getSubmissionStatusLabelAttribute(): string
    {
        return self::getSubmissionStatuses()[$this->submission_status] ?? 'Unknown';
    }

    /**
     * Get submission status color for badges
     */
    public function getSubmissionStatusColorAttribute(): string
    {
        return match ($this->submission_status) {
            self::SUBMISSION_STATUS_DRAFT => 'secondary',
            self::SUBMISSION_STATUS_READY => 'info',
            self::SUBMISSION_STATUS_SUBMITTED => 'primary',
            self::SUBMISSION_STATUS_ACCEPTED => 'success',
            self::SUBMISSION_STATUS_REJECTED => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Check if declaration can be submitted
     */
    public function canBeSubmitted(): bool
    {
        return in_array($this->submission_status, [
            self::SUBMISSION_STATUS_DRAFT,
            self::SUBMISSION_STATUS_READY,
            self::SUBMISSION_STATUS_REJECTED, // Allow resubmission after rejection
        ]);
    }

    /**
     * Check if declaration is already submitted
     */
    public function isSubmitted(): bool
    {
        return in_array($this->submission_status, [
            self::SUBMISSION_STATUS_SUBMITTED,
            self::SUBMISSION_STATUS_ACCEPTED,
        ]);
    }

    /**
     * Mark as ready for submission
     */
    public function markReady(): void
    {
        $this->update([
            'submission_status' => self::SUBMISSION_STATUS_READY,
        ]);
    }

    /**
     * Submit the declaration
     */
    public function submit(User $user, ?string $reference = null, ?string $notes = null): void
    {
        $this->update([
            'submission_status' => self::SUBMISSION_STATUS_SUBMITTED,
            'submitted_by_user_id' => $user->id,
            'submitted_at' => now(),
            'submission_reference' => $reference,
            'submission_notes' => $notes,
        ]);
    }

    /**
     * Mark submission as accepted
     */
    public function markAccepted(?string $response = null): void
    {
        $this->update([
            'submission_status' => self::SUBMISSION_STATUS_ACCEPTED,
            'submission_response_at' => now(),
            'submission_response' => $response,
        ]);
    }

    /**
     * Mark submission as rejected
     */
    public function markRejected(?string $response = null): void
    {
        $this->update([
            'submission_status' => self::SUBMISSION_STATUS_REJECTED,
            'submission_response_at' => now(),
            'submission_response' => $response,
        ]);
    }

    // ==========================================
    // Submission Scopes
    // ==========================================

    /**
     * Scope to get declarations by submission status
     */
    public function scopeSubmissionStatus($query, string $status)
    {
        return $query->where('submission_status', $status);
    }

    /**
     * Scope to get draft declarations
     */
    public function scopeDraft($query)
    {
        return $query->where('submission_status', self::SUBMISSION_STATUS_DRAFT);
    }

    /**
     * Scope to get ready declarations
     */
    public function scopeReadyToSubmit($query)
    {
        return $query->where('submission_status', self::SUBMISSION_STATUS_READY);
    }

    /**
     * Scope to get submitted declarations
     */
    public function scopeSubmitted($query)
    {
        return $query->where('submission_status', self::SUBMISSION_STATUS_SUBMITTED);
    }

    /**
     * Scope to get declarations that can be submitted
     */
    public function scopeCanSubmit($query)
    {
        return $query->whereIn('submission_status', [
            self::SUBMISSION_STATUS_DRAFT,
            self::SUBMISSION_STATUS_READY,
            self::SUBMISSION_STATUS_REJECTED,
        ]);
    }
}
