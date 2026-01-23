<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalLevy extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'tariff_chapter_id',
        'levy_name',
        'rate',
        'rate_type',
        'unit',
        'legal_reference',
        'exempt_organizations',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'exempt_organizations' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Rate types
     */
    const RATE_PERCENTAGE = 'percentage';
    const RATE_FIXED = 'fixed_amount';
    const RATE_PER_UNIT = 'per_unit';

    /**
     * Get all available rate types
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
     * Get the country this levy belongs to
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the tariff chapter this levy applies to
     */
    public function tariffChapter()
    {
        return $this->belongsTo(TariffChapter::class);
    }

    /**
     * Scope for active levies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by country
     */
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Scope to filter by chapter
     */
    public function scopeForChapter($query, $chapterId)
    {
        return $query->where('tariff_chapter_id', $chapterId);
    }

    /**
     * Check if an organization is exempt from this levy
     */
    public function isOrganizationExempt(string $organizationName): bool
    {
        if (empty($this->exempt_organizations)) {
            return false;
        }

        $nameLower = strtolower($organizationName);
        
        foreach ($this->exempt_organizations as $exempt) {
            if (str_contains($nameLower, strtolower($exempt))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate levy amount
     */
    public function calculateAmount(float $value, float $quantity = 1): float
    {
        return match ($this->rate_type) {
            self::RATE_PERCENTAGE => $value * ($this->rate / 100),
            self::RATE_FIXED => (float) $this->rate,
            self::RATE_PER_UNIT => (float) $this->rate * $quantity,
            default => 0,
        };
    }

    /**
     * Get formatted rate display
     */
    public function getFormattedRateAttribute(): string
    {
        return match ($this->rate_type) {
            self::RATE_PERCENTAGE => "{$this->rate}%",
            self::RATE_FIXED => "$" . number_format($this->rate, 2),
            self::RATE_PER_UNIT => "$" . number_format($this->rate, 4) . " per {$this->unit}",
            default => (string) $this->rate,
        };
    }

    /**
     * Find all active levies for a chapter
     */
    public static function findForChapter(int $chapterId, int $countryId): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->forCountry($countryId)
            ->forChapter($chapterId)
            ->get();
    }
}
