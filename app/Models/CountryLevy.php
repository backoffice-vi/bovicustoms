<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CountryLevy extends Model
{
    use HasFactory;

    /**
     * Rate type constants
     */
    const RATE_PERCENTAGE = 'percentage';
    const RATE_FIXED = 'fixed_amount';
    const RATE_PER_UNIT = 'per_unit';

    /**
     * Calculation basis constants
     */
    const BASIS_FOB = 'fob';
    const BASIS_CIF = 'cif';
    const BASIS_DUTY = 'duty';
    const BASIS_QUANTITY = 'quantity';
    const BASIS_WEIGHT = 'weight';

    /**
     * Common levy codes
     */
    const CODE_WHARFAGE = 'WHA';
    const CODE_CUSTOMS_DUTY = 'CUD';
    const CODE_ENVIRONMENTAL = 'ENV';
    const CODE_SERVICE_CHARGE = 'SVC';
    const CODE_STAMP_DUTY = 'STD';

    protected $fillable = [
        'country_id',
        'levy_code',
        'levy_name',
        'description',
        'rate',
        'rate_type',
        'unit',
        'calculation_basis',
        'applies_to_all_tariffs',
        'applicable_tariff_chapters',
        'exempt_tariff_codes',
        'exempt_organization_types',
        'display_order',
        'is_active',
        'legal_reference',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'applicable_tariff_chapters' => 'array',
        'exempt_tariff_codes' => 'array',
        'exempt_organization_types' => 'array',
        'applies_to_all_tariffs' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    /**
     * Get all rate types with labels
     */
    public static function getRateTypes(): array
    {
        return [
            self::RATE_PERCENTAGE => 'Percentage',
            self::RATE_FIXED => 'Fixed Amount',
            self::RATE_PER_UNIT => 'Per Unit',
        ];
    }

    /**
     * Get all calculation bases with labels
     */
    public static function getCalculationBases(): array
    {
        return [
            self::BASIS_FOB => 'FOB Value',
            self::BASIS_CIF => 'CIF Value',
            self::BASIS_DUTY => 'Customs Duty Amount',
            self::BASIS_QUANTITY => 'Quantity',
            self::BASIS_WEIGHT => 'Weight (kg)',
        ];
    }

    // ==========================================
    // Relationships
    // ==========================================

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCountry($query, int $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    public function scopeCurrentlyEffective($query)
    {
        $today = now()->toDateString();
        
        return $query->where(function ($q) use ($today) {
            $q->whereNull('effective_from')
              ->orWhere('effective_from', '<=', $today);
        })->where(function ($q) use ($today) {
            $q->whereNull('effective_until')
              ->orWhere('effective_until', '>=', $today);
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('levy_code');
    }

    // ==========================================
    // Accessors
    // ==========================================

    public function getRateTypeLabelAttribute(): string
    {
        return self::getRateTypes()[$this->rate_type] ?? 'Unknown';
    }

    public function getCalculationBasisLabelAttribute(): string
    {
        return self::getCalculationBases()[$this->calculation_basis] ?? 'Unknown';
    }

    public function getFormattedRateAttribute(): string
    {
        return match ($this->rate_type) {
            self::RATE_PERCENTAGE => number_format($this->rate, 2) . '%',
            self::RATE_FIXED => '$' . number_format($this->rate, 2),
            self::RATE_PER_UNIT => '$' . number_format($this->rate, 4) . ' per ' . ($this->unit ?? 'unit'),
            default => (string) $this->rate,
        };
    }

    public function getIsCurrentlyEffectiveAttribute(): bool
    {
        $today = now()->toDateString();
        
        if ($this->effective_from && $this->effective_from > $today) {
            return false;
        }
        
        if ($this->effective_until && $this->effective_until < $today) {
            return false;
        }
        
        return true;
    }

    // ==========================================
    // Calculation Methods
    // ==========================================

    /**
     * Calculate levy amount based on the given values
     *
     * @param float $fobValue FOB value
     * @param float $cifValue CIF value
     * @param float $dutyAmount Calculated duty amount
     * @param float $quantity Item quantity
     * @param float $weight Weight in kg
     * @return float
     */
    public function calculateAmount(
        float $fobValue = 0,
        float $cifValue = 0,
        float $dutyAmount = 0,
        float $quantity = 1,
        float $weight = 0
    ): float {
        $baseValue = match ($this->calculation_basis) {
            self::BASIS_FOB => $fobValue,
            self::BASIS_CIF => $cifValue,
            self::BASIS_DUTY => $dutyAmount,
            self::BASIS_QUANTITY => $quantity,
            self::BASIS_WEIGHT => $weight,
            default => $fobValue,
        };

        return match ($this->rate_type) {
            self::RATE_PERCENTAGE => $baseValue * ($this->rate / 100),
            self::RATE_FIXED => (float) $this->rate,
            self::RATE_PER_UNIT => (float) $this->rate * $baseValue,
            default => 0,
        };
    }

    /**
     * Check if this levy applies to a specific tariff code
     */
    public function appliesToTariffCode(string $tariffCode): bool
    {
        // If applies to all, check exemptions
        if ($this->applies_to_all_tariffs) {
            if (!empty($this->exempt_tariff_codes)) {
                foreach ($this->exempt_tariff_codes as $exempt) {
                    if ($this->matchesTariffPattern($tariffCode, $exempt)) {
                        return false;
                    }
                }
            }
            return true;
        }

        // Check if in applicable chapters
        if (!empty($this->applicable_tariff_chapters)) {
            $chapter = substr(preg_replace('/[^0-9]/', '', $tariffCode), 0, 2);
            return in_array($chapter, $this->applicable_tariff_chapters);
        }

        return false;
    }

    /**
     * Check if an organization type is exempt
     */
    public function isOrganizationTypeExempt(string $organizationType): bool
    {
        if (empty($this->exempt_organization_types)) {
            return false;
        }

        return in_array(strtolower($organizationType), array_map('strtolower', $this->exempt_organization_types));
    }

    /**
     * Match tariff code against pattern (supports wildcards)
     */
    protected function matchesTariffPattern(string $tariffCode, string $pattern): bool
    {
        $pattern = str_replace(['*', '.'], ['.*', '\\.'], $pattern);
        return (bool) preg_match('/^' . $pattern . '$/i', $tariffCode);
    }

    // ==========================================
    // Static Methods
    // ==========================================

    /**
     * Get all active levies for a country
     */
    public static function getForCountry(int $countryId): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->forCountry($countryId)
            ->currentlyEffective()
            ->ordered()
            ->get();
    }

    /**
     * Calculate all levies for a country
     */
    public static function calculateAllForCountry(
        int $countryId,
        float $fobValue,
        float $cifValue,
        float $dutyAmount,
        float $quantity = 1,
        float $weight = 0,
        ?string $tariffCode = null,
        ?string $organizationType = null
    ): array {
        $levies = static::getForCountry($countryId);
        $results = [];
        $totalLevies = 0;

        foreach ($levies as $levy) {
            // Check tariff applicability
            if ($tariffCode && !$levy->appliesToTariffCode($tariffCode)) {
                continue;
            }

            // Check organization exemption
            if ($organizationType && $levy->isOrganizationTypeExempt($organizationType)) {
                continue;
            }

            $amount = $levy->calculateAmount($fobValue, $cifValue, $dutyAmount, $quantity, $weight);
            
            $results[] = [
                'levy_code' => $levy->levy_code,
                'levy_name' => $levy->levy_name,
                'rate' => $levy->rate,
                'rate_type' => $levy->rate_type,
                'formatted_rate' => $levy->formatted_rate,
                'calculation_basis' => $levy->calculation_basis,
                'amount' => round($amount, 2),
            ];

            $totalLevies += $amount;
        }

        return [
            'levies' => $results,
            'total_levies' => round($totalLevies, 2),
        ];
    }
}
