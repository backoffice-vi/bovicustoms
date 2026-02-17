<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class OrganizationSubmissionCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'country_id',
        'credential_type',
        'web_form_target_id',
        'credentials',
        'trader_id',
        'display_name',
        'is_active',
        'last_used_at',
        'last_tested_at',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'last_tested_at' => 'datetime',
    ];

    // ==========================================
    // Constants
    // ==========================================

    const TYPE_FTP = 'ftp';
    const TYPE_WEB = 'web';

    // ==========================================
    // Relationships
    // ==========================================

    /**
     * Get the organization that owns these credentials
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the country these credentials are for
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the web form target (for web credentials only)
     */
    public function webFormTarget()
    {
        return $this->belongsTo(WebFormTarget::class);
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope to get FTP credentials only
     */
    public function scopeForFtp($query)
    {
        return $query->where('credential_type', self::TYPE_FTP);
    }

    /**
     * Scope to get web credentials only
     */
    public function scopeForWeb($query)
    {
        return $query->where('credential_type', self::TYPE_WEB);
    }

    /**
     * Scope to filter by country
     */
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Scope to filter by organization
     */
    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Scope to get active credentials only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by web form target
     */
    public function scopeForTarget($query, $targetId)
    {
        return $query->where('web_form_target_id', $targetId);
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
     * Get display name or generate one
     */
    public function getDisplayNameAttribute($value): string
    {
        if (!empty($value)) {
            return $value;
        }

        $typeName = $this->credential_type === self::TYPE_FTP ? 'FTP' : 'Web';
        $countryName = $this->country->name ?? 'Unknown';
        
        if ($this->credential_type === self::TYPE_WEB && $this->webFormTarget) {
            return "{$typeName} - {$this->webFormTarget->name}";
        }

        return "{$typeName} - {$countryName}";
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Check if this is an FTP credential
     */
    public function isFtp(): bool
    {
        return $this->credential_type === self::TYPE_FTP;
    }

    /**
     * Check if this is a web credential
     */
    public function isWeb(): bool
    {
        return $this->credential_type === self::TYPE_WEB;
    }

    /**
     * Get FTP credentials in a structured format
     */
    public function getFtpCredentials(): array
    {
        $creds = $this->decrypted_credentials;
        
        if (!$creds || !$this->isFtp()) {
            return [];
        }

        return [
            'trader_id' => $this->trader_id ?? ($creds['trader_id'] ?? ''),
            'username' => $creds['username'] ?? '',
            'password' => $creds['password'] ?? '',
            'email' => $creds['email'] ?? '',
        ];
    }

    /**
     * Get web credentials for Playwright
     */
    public function getPlaywrightCredentials(): array
    {
        $creds = $this->decrypted_credentials;
        
        if (!$creds || !$this->isWeb()) {
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

    /**
     * Mark credentials as used
     */
    public function markUsed(): void
    {
        if ($this->exists) {
            $this->update(['last_used_at' => now()]);
        }
    }

    /**
     * Mark credentials as tested
     */
    public function markTested(): void
    {
        if ($this->exists) {
            $this->update(['last_tested_at' => now()]);
        }
    }

    /**
     * Update specific credential fields without overwriting all
     */
    public function updateCredentialFields(array $newFields): void
    {
        $current = $this->decrypted_credentials ?? [];
        
        // Merge new fields, keeping existing values for fields not provided
        foreach ($newFields as $key => $value) {
            if ($value !== null && $value !== '') {
                $current[$key] = $value;
            }
        }
        
        $this->credentials = $current;
        $this->save();
    }

    /**
     * Check if credentials are complete for FTP submission
     */
    public function hasCompleteFtpCredentials(): bool
    {
        if (!$this->isFtp()) {
            return false;
        }

        $creds = $this->getFtpCredentials();
        
        return !empty($creds['trader_id']) 
            && !empty($creds['username']) 
            && !empty($creds['password']);
    }

    /**
     * Check if credentials are complete for web submission
     */
    public function hasCompleteWebCredentials(): bool
    {
        if (!$this->isWeb()) {
            return false;
        }

        $creds = $this->getPlaywrightCredentials();
        
        return !empty($creds['username']) && !empty($creds['password']);
    }
}
