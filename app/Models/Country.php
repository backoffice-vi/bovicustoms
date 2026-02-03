<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'currency_code',
        'is_active',
        'flag_emoji',
        'customs_form_template',
        'default_insurance_percentage',
        'default_insurance_method',
        // FTP submission settings
        'ftp_enabled',
        'ftp_host',
        'ftp_port',
        'ftp_passive_mode',
        'ftp_base_path',
        'ftp_file_format',
        'submission_methods',
        'ftp_notification_email',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_insurance_percentage' => 'decimal:2',
        'ftp_enabled' => 'boolean',
        'ftp_passive_mode' => 'boolean',
        'ftp_port' => 'integer',
        'submission_methods' => 'array',
    ];

    /**
     * Get the default insurance settings for shipments to this country
     */
    public function getInsuranceDefaults(): array
    {
        return [
            'method' => $this->default_insurance_method ?? 'percentage',
            'percentage' => $this->default_insurance_percentage ?? 1.00,
        ];
    }

    /**
     * Get all customs codes for this country
     */
    public function customsCodes()
    {
        return $this->hasMany(CustomsCode::class);
    }

    /**
     * Get all organizations using this country
     */
    public function organizations()
    {
        return $this->hasMany(Organization::class);
    }

    /**
     * Get all invoices for this country
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get all form templates for this country
     */
    public function formTemplates()
    {
        return $this->hasMany(CountryFormTemplate::class);
    }

    /**
     * Get all support documents for this country
     */
    public function supportDocuments()
    {
        return $this->hasMany(CountrySupportDocument::class);
    }

    /**
     * Get all reference data for this country (carriers, ports, CPC codes, etc.)
     */
    public function referenceData()
    {
        return $this->hasMany(CountryReferenceData::class);
    }

    /**
     * Get reference data of a specific type
     */
    public function getReferenceData(string $type)
    {
        return $this->referenceData()
            ->where('reference_type', $type)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();
    }

    /**
     * Scope to only include active countries
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ==========================================
    // FTP Submission Methods
    // ==========================================

    /**
     * Get all web form targets for this country
     */
    public function webFormTargets()
    {
        return $this->hasMany(WebFormTarget::class);
    }

    /**
     * Get all organization submission credentials for this country
     */
    public function submissionCredentials()
    {
        return $this->hasMany(OrganizationSubmissionCredential::class);
    }

    /**
     * Check if FTP submission is enabled for this country
     */
    public function isFtpEnabled(): bool
    {
        return $this->ftp_enabled && !empty($this->ftp_host);
    }

    /**
     * Check if web submission is enabled for this country
     */
    public function isWebEnabled(): bool
    {
        $methods = $this->getSubmissionMethods();
        return in_array('web', $methods) && $this->webFormTargets()->active()->exists();
    }

    /**
     * Get available submission methods for this country
     */
    public function getSubmissionMethods(): array
    {
        // If explicitly set, return that
        if (!empty($this->submission_methods)) {
            return $this->submission_methods;
        }

        // Default: web only (for backward compatibility)
        $methods = ['web'];
        
        // Add FTP if enabled
        if ($this->ftp_enabled && !empty($this->ftp_host)) {
            $methods[] = 'ftp';
        }

        return $methods;
    }

    /**
     * Get FTP connection settings
     */
    public function getFtpSettings(): array
    {
        return [
            'host' => $this->ftp_host,
            'port' => $this->ftp_port ?? 21,
            'passive' => $this->ftp_passive_mode ?? true,
            'base_path' => $this->ftp_base_path ?? '/',
            'file_format' => $this->ftp_file_format ?? 'caps_t12',
            'notification_email' => $this->ftp_notification_email,
        ];
    }

    /**
     * Check if country supports a specific submission method
     */
    public function supportsSubmissionMethod(string $method): bool
    {
        return in_array($method, $this->getSubmissionMethods());
    }

    /**
     * Scope to filter countries with FTP enabled
     */
    public function scopeFtpEnabled($query)
    {
        return $query->where('ftp_enabled', true)
                    ->whereNotNull('ftp_host');
    }
}
