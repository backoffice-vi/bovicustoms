<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class WebFormTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'name',
        'code',
        'base_url',
        'login_url',
        'credentials',
        'auth_type',
        'workflow_steps',
        'config',
        'is_active',
        'requires_ai',
        'last_mapped_at',
        'last_tested_at',
        'notes',
    ];

    protected $casts = [
        'workflow_steps' => 'array',
        'config' => 'array',
        'is_active' => 'boolean',
        'requires_ai' => 'boolean',
        'last_mapped_at' => 'datetime',
        'last_tested_at' => 'datetime',
    ];

    // ==========================================
    // Relationships
    // ==========================================

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function pages()
    {
        return $this->hasMany(WebFormPage::class)->orderBy('sequence_order');
    }

    public function submissions()
    {
        return $this->hasMany(WebFormSubmission::class);
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    // ==========================================
    // Accessors & Mutators
    // ==========================================

    /**
     * Get decrypted credentials
     */
    public function getDecryptedCredentialsAttribute(): ?array
    {
        if (empty($this->credentials)) {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString($this->credentials);
            return json_decode($decrypted, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted credentials
     */
    public function setCredentialsAttribute($value)
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        
        if (!empty($value)) {
            $this->attributes['credentials'] = Crypt::encryptString($value);
        } else {
            $this->attributes['credentials'] = null;
        }
    }

    /**
     * Get full login URL
     */
    public function getFullLoginUrlAttribute(): string
    {
        return rtrim($this->base_url, '/') . '/' . ltrim($this->login_url, '/');
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Get all field mappings for this target
     */
    public function getAllFieldMappings()
    {
        return WebFormFieldMapping::whereIn('web_form_page_id', $this->pages->pluck('id'))
            ->where('is_active', true)
            ->orderBy('tab_order')
            ->get();
    }

    /**
     * Get the login page
     */
    public function getLoginPage(): ?WebFormPage
    {
        return $this->pages()->where('page_type', 'login')->first();
    }

    /**
     * Get form pages in order
     */
    public function getFormPages()
    {
        return $this->pages()->where('page_type', 'form')->orderBy('sequence_order')->get();
    }

    /**
     * Check if this target is fully mapped
     */
    public function isMapped(): bool
    {
        return $this->last_mapped_at !== null && $this->pages()->count() > 0;
    }

    /**
     * Update last tested timestamp
     */
    public function markTested(): void
    {
        $this->update(['last_tested_at' => now()]);
    }

    /**
     * Update last mapped timestamp
     */
    public function markMapped(): void
    {
        $this->update(['last_mapped_at' => now()]);
    }

    /**
     * Get credentials for Playwright
     */
    public function getPlaywrightCredentials(): array
    {
        $creds = $this->decrypted_credentials;
        
        if (!$creds) {
            return [];
        }

        return [
            'username' => $creds['username'] ?? '',
            'password' => $creds['password'] ?? '',
            'username_field' => $creds['username_field'] ?? 'input[name="username"]',
            'password_field' => $creds['password_field'] ?? 'input[name="password"]',
            'submit_selector' => $creds['submit_selector'] ?? 'button[type="submit"]',
        ];
    }
}
