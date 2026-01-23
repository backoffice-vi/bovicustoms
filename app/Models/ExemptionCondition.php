<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExemptionCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'exemption_category_id',
        'condition_type',
        'description',
        'requirement_text',
        'is_mandatory',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    /**
     * Condition types
     */
    const TYPE_TIME_LIMIT = 'time_limit';
    const TYPE_QUANTITY = 'quantity';
    const TYPE_PURPOSE = 'purpose';
    const TYPE_CERTIFICATE = 'certificate';
    const TYPE_REGISTRATION = 'registration';
    const TYPE_NOTIFICATION = 'notification';
    const TYPE_RESIDENCY = 'residency';
    const TYPE_OWNERSHIP = 'ownership';
    const TYPE_OTHER = 'other';

    /**
     * Get all available condition types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_TIME_LIMIT => 'Time Limit',
            self::TYPE_QUANTITY => 'Quantity Limit',
            self::TYPE_PURPOSE => 'Purpose Restriction',
            self::TYPE_CERTIFICATE => 'Certificate Required',
            self::TYPE_REGISTRATION => 'Registration Required',
            self::TYPE_NOTIFICATION => 'Notification Required',
            self::TYPE_RESIDENCY => 'Residency Requirement',
            self::TYPE_OWNERSHIP => 'Ownership Requirement',
            self::TYPE_OTHER => 'Other',
        ];
    }

    /**
     * Get the exemption category this condition belongs to
     */
    public function exemptionCategory()
    {
        return $this->belongsTo(ExemptionCategory::class);
    }

    /**
     * Get human-readable type name
     */
    public function getTypeNameAttribute(): string
    {
        return self::getTypes()[$this->condition_type] ?? $this->condition_type;
    }

    /**
     * Scope for mandatory conditions
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Scope for optional conditions
     */
    public function scopeOptional($query)
    {
        return $query->where('is_mandatory', false);
    }
}
