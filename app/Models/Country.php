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
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

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
     * Scope to only include active countries
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
