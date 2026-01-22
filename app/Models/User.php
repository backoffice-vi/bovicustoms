<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'organization_id',
        'is_individual',
        'current_country_id',
        'onboarding_completed',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_individual' => 'boolean',
        'onboarding_completed' => 'boolean',
    ];

    /**
     * Get the organization this user belongs to
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get organizations this user is a member of (many-to-many)
     */
    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
                    ->withPivot('role', 'invited_at', 'joined_at')
                    ->withTimestamps();
    }

    /**
     * Get the current country for individual users
     */
    public function currentCountry()
    {
        return $this->belongsTo(Country::class, 'current_country_id');
    }

    /**
     * Get invoices created by this user
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user belongs to an organization
     */
    public function hasOrganization()
    {
        return !is_null($this->organization_id);
    }

    /**
     * Get the effective organization (direct or first from many-to-many)
     */
    public function getEffectiveOrganization()
    {
        if ($this->organization_id) {
            return $this->organization;
        }
        
        return $this->organizations()->first();
    }
}
