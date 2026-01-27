<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'currency_code',
        'is_active',
        'flag_emoji',
        'customs_form_template',
        'default_insurance_percentage',
        'default_insurance_method',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_insurance_percentage' => 'decimal:2',
    ];

    /**
     * Get the default insurance settings for shipments to this country
     */
    public function getInsuranceDefaults(): array
    {
        return [
            'method' => $this->default_insurance_method ?? 'percentage',
            'percentage' => $this->default_insurance_percentage ?? 1.00,
        ];
    }

    /**
     * Get all customs codes for this country
     */
    public function customsCodes()
    {
        return $this->hasMany(CustomsCode::class);
    }

    /**
     * Get all organizations using this country
     */
    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }

    /**
     * Get all invoices for this country
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get all form templates for this country
     */
    public function formTemplates()
    {
        return $this->hasMany(CountryFormTemplate::class);
    }

    /**
     * Get all support documents for this country
     */
    public function supportDocuments()
    {
        return $this->hasMany(CountrySupportDocument::class);
    }

    /**
     * Get all reference data for this country (carriers, ports, CPC codes, etc.)
     */
    public function referenceData()
    {
        return $this->hasMany(CountryReferenceData::class);
    }

    /**
     * Get reference data of a specific type
     */
    public function getReferenceData(string $type)
    {
        return $this->referenceData()
            ->where('reference_type', $type)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    /**
     * Scope to only include active countries
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
