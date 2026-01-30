<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'country_id',
        'user_id',
        'invoice_number',
        'invoice_date',
        'total_amount',
        'status',
        'items',
        'parsed_data',
        'processed',
        'source_type',
        'source_file_path',
        'extracted_text',
        'extraction_meta',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'total_amount' => 'decimal:2',
        'items' => 'array',
        'parsed_data' => 'array',
        'processed' => 'boolean',
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
                    // Use table-qualified column name to avoid ambiguity in joins
                    $builder->where('invoices.organization_id', $user->organization_id);
                } else if ($user->is_individual) {
                    $builder->where('invoices.user_id', $user->id);
                }
            }
        });
    }

    /**
     * Get the organization this invoice belongs to
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the country for this invoice
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the user who created this invoice
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get declaration forms for this invoice
     */
    public function declarationForms()
    {
        return $this->hasMany(DeclarationForm::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get shipments that include this invoice
     */
    public function shipments()
    {
        return $this->belongsToMany(Shipment::class, 'shipment_invoices')
            ->withPivot(['prorated_freight', 'prorated_insurance', 'invoice_fob'])
            ->withTimestamps();
    }
}
