<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Country-level customs-duty calculation policy with effective dates.
 *
 * Used by DutyPolicyResolver to decide whether to compute customs duty on
 * FOB or CIF for a given country and declaration date. Wharfage and other
 * levies have their own per-row basis on country_levies; this row only
 * controls the CUD (customs duty) base.
 *
 * Example: BVI cost-of-living measure May–July 2026 inserts one row with
 * basis = 'fob', effective_from = 2026-05-01, effective_until = 2026-07-31.
 * Outside that window, the resolver falls back to BASIS_CIF.
 */
class CountryDutyPolicy extends Model
{
    use HasFactory;

    public const BASIS_FOB = 'fob';
    public const BASIS_CIF = 'cif';

    /** Default basis when no row matches the declaration date. */
    public const DEFAULT_BASIS = self::BASIS_CIF;

    protected $fillable = [
        'country_id',
        'basis',
        'effective_from',
        'effective_until',
        'source',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    public static function getBasisOptions(): array
    {
        return [
            self::BASIS_FOB => 'FOB Value (Free on Board)',
            self::BASIS_CIF => 'CIF Value (Cost + Insurance + Freight)',
        ];
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCountry($query, int $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Active rows whose [effective_from, effective_until] window contains $date.
     * effective_until is inclusive; null means open-ended.
     */
    public function scopeEffectiveOn($query, string $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_until')
                  ->orWhere('effective_until', '>=', $date);
            });
    }
}
