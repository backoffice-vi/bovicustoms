<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeContact extends Model
{
    use HasFactory;

    /**
     * Contact type constants
     */
    const TYPE_SHIPPER = 'shipper';
    const TYPE_CONSIGNEE = 'consignee';
    const TYPE_BROKER = 'broker';
    const TYPE_NOTIFY_PARTY = 'notify_party';
    const TYPE_BANK = 'bank';
    const TYPE_OTHER = 'other';

    protected $fillable = [
        'organization_id',
        'user_id',
        'contact_type',
        'company_name',
        'contact_name',
        'address_line_1',
        'address_line_2',
        'city',
        'state_province',
        'postal_code',
        'country_id',
        'phone',
        'fax',
        'email',
        'tax_id',
        'license_number',
        'customs_registration_id', // Importer ID for consignees, Declarant ID for brokers
        'bank_name',
        'bank_account',
        'bank_routing',
        'notes',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Boot method to apply tenant scope
     */
    protected static function boot()
    {
        parent::boot();

        // Apply global scope to filter by tenant
        static::addGlobalScope('tenant', function ($builder) {
            if (auth()->check()) {
                $user = auth()->user();
                if ($user->organization_id) {
                    // Use table-qualified column name to avoid ambiguity in joins
                    $builder->where('trade_contacts.organization_id', $user->organization_id);
                } elseif ($user->is_individual) {
                    $builder->where('trade_contacts.user_id', $user->id);
                }
            }
        });
    }

    /**
     * Get all contact types with labels
     */
    public static function getContactTypes(): array
    {
        return [
            self::TYPE_SHIPPER => 'Shipper / Exporter',
            self::TYPE_CONSIGNEE => 'Consignee / Importer',
            self::TYPE_BROKER => 'Customs Broker',
            self::TYPE_NOTIFY_PARTY => 'Notify Party',
            self::TYPE_BANK => 'Bank',
            self::TYPE_OTHER => 'Other',
        ];
    }

    /**
     * Get the label for the contact type
     */
    public function getContactTypeLabelAttribute(): string
    {
        return self::getContactTypes()[$this->contact_type] ?? 'Unknown';
    }

    /**
     * Get formatted full address
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->state_province,
            $this->postal_code,
            $this->country?->name,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get display name (company + contact person)
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->contact_name) {
            return "{$this->company_name} ({$this->contact_name})";
        }
        return $this->company_name;
    }

    /**
     * Get trader ID (alias for customs_registration_id)
     * Used for FTP submission T12 file generation
     */
    public function getTraderIdAttribute(): ?string
    {
        return $this->customs_registration_id;
    }

    // ==========================================
    // Relationships
    // ==========================================

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    // ==========================================
    // Scopes
    // ==========================================

    /**
     * Scope to filter by contact type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('contact_type', $type);
    }

    /**
     * Scope for shippers
     */
    public function scopeShippers($query)
    {
        return $query->where('contact_type', self::TYPE_SHIPPER);
    }

    /**
     * Scope for consignees
     */
    public function scopeConsignees($query)
    {
        return $query->where('contact_type', self::TYPE_CONSIGNEE);
    }

    /**
     * Scope for brokers
     */
    public function scopeBrokers($query)
    {
        return $query->where('contact_type', self::TYPE_BROKER);
    }

    /**
     * Scope for notify parties
     */
    public function scopeNotifyParties($query)
    {
        return $query->where('contact_type', self::TYPE_NOTIFY_PARTY);
    }

    /**
     * Scope for banks
     */
    public function scopeBanks($query)
    {
        return $query->where('contact_type', self::TYPE_BANK);
    }

    /**
     * Scope for default contacts
     */
    public function scopeDefaults($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to get default contact of a specific type
     */
    public function scopeDefaultOfType($query, string $type)
    {
        return $query->where('contact_type', $type)->where('is_default', true);
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Set this contact as default for its type (unset others)
     */
    public function setAsDefault(): void
    {
        // Unset other defaults of the same type
        static::withoutGlobalScopes()
            ->where('contact_type', $this->contact_type)
            ->where('id', '!=', $this->id)
            ->when($this->organization_id, function ($q) {
                $q->where('organization_id', $this->organization_id);
            })
            ->when(!$this->organization_id, function ($q) {
                $q->where('user_id', $this->user_id);
            })
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Convert to array for form filling
     */
    public function toFormData(): array
    {
        return [
            'company_name' => $this->company_name,
            'contact_name' => $this->contact_name,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'state_province' => $this->state_province,
            'postal_code' => $this->postal_code,
            'country' => $this->country?->name,
            'country_code' => $this->country?->code,
            'full_address' => $this->full_address,
            'phone' => $this->phone,
            'fax' => $this->fax,
            'email' => $this->email,
            'tax_id' => $this->tax_id,
            'license_number' => $this->license_number,
            'customs_registration_id' => $this->customs_registration_id,
            'bank_name' => $this->bank_name,
            'bank_account' => $this->bank_account,
            'bank_routing' => $this->bank_routing,
        ];
    }
}
