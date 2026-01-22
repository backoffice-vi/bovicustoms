<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'country_id',
        'subscription_plan',
        'subscription_status',
        'trial_ends_at',
        'subscription_ends_at',
        'invoice_limit',
        'settings',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'settings' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($organization) {
            if (empty($organization->slug)) {
                $organization->slug = Str::slug($organization->name);
            }
        });
    }

    /**
     * Get the country this organization primarily operates in
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get all users in this organization
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_user')
                    ->withPivot('role', 'invited_at', 'joined_at')
                    ->withTimestamps();
    }

    /**
     * Get all invoices for this organization
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get all declaration forms for this organization
     */
    public function declarationForms()
    {
        return $this->hasMany(DeclarationForm::class);
    }

    /**
     * Check if organization subscription is active
     */
    public function isSubscriptionActive()
    {
        if ($this->subscription_status === 'trial') {
            return $this->trial_ends_at && $this->trial_ends_at->isFuture();
        }
        
        return $this->subscription_status === 'active';
    }

    /**
     * Check if organization is on trial
     */
    public function isOnTrial()
    {
        return $this->subscription_status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Get owner of the organization
     */
    public function owner()
    {
        return $this->users()->wherePivot('role', 'owner')->first();
    }
}
