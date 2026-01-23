<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassificationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'country_id',
        'name',
        'rule_type',
        'condition',
        'target_code',
        'instruction',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Rule types
     */
    const TYPE_KEYWORD = 'keyword';
    const TYPE_CATEGORY = 'category';
    const TYPE_OVERRIDE = 'override';
    const TYPE_INSTRUCTION = 'instruction';

    /**
     * Get the organization that owns the rule
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the country for the rule
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Scope to get active rules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get rules for a specific organization
     */
    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where(function ($q) use ($organizationId) {
            $q->where('organization_id', $organizationId)
              ->orWhereNull('organization_id'); // Global rules
        });
    }

    /**
     * Scope to get rules for a specific country
     */
    public function scopeForCountry($query, $countryId)
    {
        return $query->where(function ($q) use ($countryId) {
            $q->where('country_id', $countryId)
              ->orWhereNull('country_id'); // All countries
        });
    }

    /**
     * Scope to order by priority (highest first)
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Check if this rule matches the given item description
     */
    public function matches(string $itemDescription): bool
    {
        $itemDescription = strtolower($itemDescription);
        $condition = strtolower($this->condition);

        switch ($this->rule_type) {
            case self::TYPE_KEYWORD:
                // Check if any keyword in condition matches
                $keywords = array_map('trim', explode(',', $condition));
                foreach ($keywords as $keyword) {
                    if (str_contains($itemDescription, $keyword)) {
                        return true;
                    }
                }
                return false;

            case self::TYPE_CATEGORY:
                // Category rules match based on keywords
                $keywords = array_map('trim', explode(',', $condition));
                foreach ($keywords as $keyword) {
                    if (str_contains($itemDescription, $keyword)) {
                        return true;
                    }
                }
                return false;

            case self::TYPE_OVERRIDE:
                // Exact or near-exact match
                return $itemDescription === $condition || 
                       str_contains($itemDescription, $condition);

            case self::TYPE_INSTRUCTION:
                // Instructions always apply (they're context, not conditions)
                return true;

            default:
                return false;
        }
    }

    /**
     * Get human-readable rule type
     */
    public function getRuleTypeLabel(): string
    {
        return match ($this->rule_type) {
            self::TYPE_KEYWORD => 'Keyword Match',
            self::TYPE_CATEGORY => 'Category Rule',
            self::TYPE_OVERRIDE => 'Override',
            self::TYPE_INSTRUCTION => 'General Instruction',
            default => ucfirst($this->rule_type),
        };
    }

    /**
     * Get all active rules applicable to an item
     */
    public static function getApplicableRules(string $itemDescription, ?int $organizationId = null, ?int $countryId = null): array
    {
        $query = self::active()->byPriority();

        if ($organizationId) {
            $query->forOrganization($organizationId);
        }

        if ($countryId) {
            $query->forCountry($countryId);
        }

        $rules = $query->get();
        $applicable = [];

        foreach ($rules as $rule) {
            if ($rule->matches($itemDescription)) {
                $applicable[] = $rule;
            }
        }

        return $applicable;
    }

    /**
     * Get all instruction-type rules (for AI context)
     */
    public static function getInstructions(?int $organizationId = null, ?int $countryId = null): array
    {
        $query = self::active()
            ->where('rule_type', self::TYPE_INSTRUCTION)
            ->byPriority();

        if ($organizationId) {
            $query->forOrganization($organizationId);
        }

        if ($countryId) {
            $query->forCountry($countryId);
        }

        return $query->pluck('instruction')->toArray();
    }
}
