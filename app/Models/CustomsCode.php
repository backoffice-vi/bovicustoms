<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomsCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'code',
        'hs_code_version',
        'description',
        'duty_rate',
        'duty_type',
        'specific_duty_amount',
        'specific_duty_unit',
        // Hierarchical fields
        'tariff_chapter_id',
        'parent_code',
        'code_level',
        // Units
        'unit_of_measurement',
        'unit_secondary',
        // Rates
        'special_rate',
        'notes',
        // Classification aids
        'classification_keywords',
        'applicable_note_ids',
        'applicable_exemption_ids',
        'similar_code_ids',
        'inclusion_hints',
    ];

    protected $casts = [
        'duty_rate' => 'decimal:2',
        'special_rate' => 'decimal:2',
        'specific_duty_amount' => 'decimal:2',
        'classification_keywords' => 'array',
        'applicable_note_ids' => 'array',
        'applicable_exemption_ids' => 'array',
        'similar_code_ids' => 'array',
    ];

    /**
     * Duty types
     */
    const DUTY_TYPE_AD_VALOREM = 'ad_valorem';  // Percentage of value
    const DUTY_TYPE_SPECIFIC = 'specific';      // Fixed amount per unit
    const DUTY_TYPE_COMPOUND = 'compound';      // Both percentage and fixed amount

    /**
     * Code levels
     */
    const LEVEL_CHAPTER = 'chapter';
    const LEVEL_HEADING = 'heading';
    const LEVEL_SUBHEADING = 'subheading';
    const LEVEL_ITEM = 'item';

    /**
     * Fields to track for history
     */
    protected static array $trackableFields = ['code', 'description', 'duty_rate', 'hs_code_version'];

    /**
     * Current law document ID for tracking (set externally before update)
     */
    public ?int $trackingLawDocumentId = null;

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Track changes on update (when not coming from LawDocumentProcessor)
        static::updating(function (CustomsCode $model) {
            // Skip if tracking is handled externally (e.g., by LawDocumentProcessor)
            if ($model->trackingLawDocumentId !== null) {
                return;
            }

            // Log changes for each tracked field
            foreach (static::$trackableFields as $field) {
                if ($model->isDirty($field)) {
                    CustomsCodeHistory::logChange(
                        $model->id,
                        $field,
                        $model->getOriginal($field),
                        $model->$field,
                        null, // No law document
                        auth()->id()
                    );
                }
            }
        });
    }

    /**
     * Get the country this code belongs to
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the tariff chapter this code belongs to
     */
    public function tariffChapter()
    {
        return $this->belongsTo(TariffChapter::class);
    }

    /**
     * Get the parent code (for hierarchical structure)
     */
    public function parent()
    {
        return $this->belongsTo(CustomsCode::class, 'parent_code', 'code')
            ->where('country_id', $this->country_id);
    }

    /**
     * Get child codes (for hierarchical structure)
     */
    public function children()
    {
        return $this->hasMany(CustomsCode::class, 'parent_code', 'code')
            ->where('country_id', $this->country_id);
    }

    /**
     * Get the history entries for this code
     */
    public function history()
    {
        return $this->hasMany(CustomsCodeHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Scope to filter by country
     */
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Scope to search codes by description or code
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('code', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
    }

    /**
     * Scope to filter by chapter
     */
    public function scopeForChapter($query, $chapterId)
    {
        return $query->where('tariff_chapter_id', $chapterId);
    }

    /**
     * Scope to filter by code level
     */
    public function scopeOfLevel($query, $level)
    {
        return $query->where('code_level', $level);
    }

    /**
     * Scope to search by keywords
     */
    public function scopeMatchingKeywords($query, array $keywords)
    {
        return $query->where(function ($q) use ($keywords) {
            foreach ($keywords as $keyword) {
                $q->orWhereJsonContains('classification_keywords', strtolower($keyword));
            }
        });
    }

    /**
     * Update with history tracking from a law document
     */
    public function updateWithHistory(array $data, ?int $lawDocumentId = null): bool
    {
        // Track changes for each field
        foreach (static::$trackableFields as $field) {
            if (isset($data[$field]) && $this->$field != $data[$field]) {
                CustomsCodeHistory::logChange(
                    $this->id,
                    $field,
                    $this->$field,
                    $data[$field],
                    $lawDocumentId,
                    auth()->id()
                );
            }
        }

        // Mark that tracking is handled externally
        $this->trackingLawDocumentId = $lawDocumentId;
        
        return $this->update($data);
    }

    /**
     * Get applicable exemptions for this code
     */
    public function getApplicableExemptions(): \Illuminate\Database\Eloquent\Collection
    {
        if (!empty($this->applicable_exemption_ids)) {
            return ExemptionCategory::whereIn('id', $this->applicable_exemption_ids)
                ->active()
                ->with('conditions')
                ->get();
        }

        // Fallback: find by pattern matching
        return ExemptionCategory::findForCode($this->code, $this->country_id);
    }

    /**
     * Get additional levies for this code's chapter
     */
    public function getAdditionalLevies(): \Illuminate\Support\Collection
    {
        if (!$this->tariff_chapter_id) {
            return collect();
        }

        return AdditionalLevy::findForChapter($this->tariff_chapter_id, $this->country_id);
    }

    /**
     * Get all applicable notes (section + chapter)
     */
    public function getAllApplicableNotes(): array
    {
        if ($this->tariffChapter) {
            return $this->tariffChapter->getAllApplicableNotes();
        }

        return [];
    }

    /**
     * Calculate total duty including levies
     */
    public function calculateTotalDuty(float $value, float $quantity = 1, ?string $organization = null): array
    {
        $baseDuty = $this->duty_rate ? $value * ($this->duty_rate / 100) : 0;
        
        $levies = [];
        $totalLevies = 0;
        
        foreach ($this->getAdditionalLevies() as $levy) {
            if ($organization && $levy->isOrganizationExempt($organization)) {
                continue;
            }
            
            $levyAmount = $levy->calculateAmount($value, $quantity);
            $levies[] = [
                'name' => $levy->levy_name,
                'amount' => $levyAmount,
                'rate' => $levy->formatted_rate,
            ];
            $totalLevies += $levyAmount;
        }

        return [
            'base_duty_rate' => $this->duty_rate,
            'base_duty_amount' => $baseDuty,
            'additional_levies' => $levies,
            'total_levies' => $totalLevies,
            'total_duty' => $baseDuty + $totalLevies,
        ];
    }

    /**
     * Get similar codes
     */
    public function getSimilarCodes(): \Illuminate\Database\Eloquent\Collection
    {
        if (!empty($this->similar_code_ids)) {
            return static::whereIn('id', $this->similar_code_ids)->get();
        }

        return collect();
    }

    /**
     * Extract chapter number from code
     */
    public function getChapterNumberAttribute(): ?string
    {
        if (preg_match('/^(\d{2})/', $this->code, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get formatted unit display
     */
    public function getFormattedUnitAttribute(): ?string
    {
        if ($this->unit_of_measurement && $this->unit_secondary) {
            return "{$this->unit_of_measurement} and {$this->unit_secondary}";
        }
        return $this->unit_of_measurement;
    }

    /**
     * Check if code has special rate
     */
    public function hasSpecialRate(): bool
    {
        return $this->special_rate !== null;
    }

    /**
     * Check if this is a specific duty (fixed amount)
     */
    public function isSpecificDuty(): bool
    {
        return $this->duty_type === self::DUTY_TYPE_SPECIFIC;
    }

    /**
     * Check if this is an ad valorem duty (percentage)
     */
    public function isAdValoremDuty(): bool
    {
        return $this->duty_type === self::DUTY_TYPE_AD_VALOREM;
    }

    /**
     * Check if this is a compound duty (both)
     */
    public function isCompoundDuty(): bool
    {
        return $this->duty_type === self::DUTY_TYPE_COMPOUND;
    }

    /**
     * Get formatted duty rate display
     */
    public function getFormattedDutyAttribute(): string
    {
        if ($this->duty_type === self::DUTY_TYPE_SPECIFIC) {
            $amount = number_format($this->specific_duty_amount, 2);
            $unit = $this->specific_duty_unit ?? 'each';
            return "\${$amount} {$unit}";
        }
        
        if ($this->duty_type === self::DUTY_TYPE_COMPOUND) {
            $percentage = number_format($this->duty_rate, 1) . '%';
            $amount = number_format($this->specific_duty_amount, 2);
            $unit = $this->specific_duty_unit ?? 'each';
            return "{$percentage} + \${$amount} {$unit}";
        }
        
        // Ad valorem (percentage)
        if ($this->duty_rate == 0) {
            return 'Free';
        }
        return number_format($this->duty_rate, 1) . '%';
    }
}
