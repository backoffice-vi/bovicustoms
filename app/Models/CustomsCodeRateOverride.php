<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Time-bounded duty-rate override for a specific customs code.
 *
 * Stored as a percentage to match `customs_codes.duty_rate` (e.g. 5.000 = 5%).
 * Used for a "basket of goods" where government temporarily reduces rates.
 *
 * The DutyPolicyResolver picks the most-recently effective active row for
 * (country, customs_code, declaration_date) and uses override_rate instead
 * of the base rate on customs_codes. Original rates are never mutated, so
 * the system reverts automatically when the window expires.
 */
class CustomsCodeRateOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'customs_code_id',
        'override_rate',
        'effective_from',
        'effective_until',
        'source',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'override_rate' => 'decimal:3',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function customsCode()
    {
        return $this->belongsTo(CustomsCode::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCountry($query, int $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    public function scopeForCustomsCode($query, int $customsCodeId)
    {
        return $query->where('customs_code_id', $customsCodeId);
    }

    public function scopeEffectiveOn($query, string $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>=', $date);
            });
    }
}
