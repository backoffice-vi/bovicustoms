<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExemptionCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'name',
        'description',
        'legal_reference',
        'applies_to_patterns',
        'is_active',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'applies_to_patterns' => 'array',
        'is_active' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    /**
     * Get the country this exemption belongs to
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the conditions for this exemption
     */
    public function conditions()
    {
        return $this->hasMany(ExemptionCondition::class);
    }

    /**
     * Get mandatory conditions
     */
    public function mandatoryConditions()
    {
        return $this->conditions()->where('is_mandatory', true);
    }

    /**
     * Scope for active exemptions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            });
    }

    /**
     * Scope to filter by country
     */
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Check if a tariff code matches this exemption's patterns
     */
    public function matchesCode(string $code): bool
    {
        if (empty($this->applies_to_patterns)) {
            return false;
        }

        foreach ($this->applies_to_patterns as $pattern) {
            $regex = '/^' . str_replace(
                ['*', '?'],
                ['.*', '.'],
                preg_quote($pattern, '/')
            ) . '$/i';
            
            if (preg_match($regex, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find all exemptions that apply to a given code
     */
    public static function findForCode(string $code, int $countryId): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->forCountry($countryId)
            ->with('conditions')
            ->get()
            ->filter(function ($exemption) use ($code) {
                return $exemption->matchesCode($code);
            });
    }

    /**
     * Check if this exemption is currently valid
     */
    public function isCurrentlyValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->valid_from && $this->valid_from > $now) {
            return false;
        }

        if ($this->valid_until && $this->valid_until < $now) {
            return false;
        }

        return true;
    }
}
