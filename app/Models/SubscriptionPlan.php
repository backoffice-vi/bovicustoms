<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'billing_period',
        'invoice_limit',
        'country_limit',
        'team_member_limit',
        'features',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to only include active plans
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if plan has unlimited invoices
     */
    public function hasUnlimitedInvoices()
    {
        return is_null($this->invoice_limit);
    }

    /**
     * Check if plan has unlimited countries
     */
    public function hasUnlimitedCountries()
    {
        return is_null($this->country_limit);
    }

    /**
     * Check if plan has unlimited team members
     */
    public function hasUnlimitedTeamMembers()
    {
        return is_null($this->team_member_limit);
    }
}
