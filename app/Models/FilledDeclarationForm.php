<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FilledDeclarationForm extends Model
{
    use HasFactory;

    /**
     * Status constants
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETE = 'complete';
    const STATUS_ERROR = 'error';

    /**
     * Output format constants
     */
    const OUTPUT_WEB = 'web';
    const OUTPUT_PDF_OVERLAY = 'pdf_overlay';
    const OUTPUT_PDF_FILLABLE = 'pdf_fillable';
    const OUTPUT_DATA_ONLY = 'data_only';

    protected $fillable = [
        'declaration_form_id',
        'country_form_template_id',
        'organization_id',
        'user_id',
        'shipper_contact_id',
        'consignee_contact_id',
        'broker_contact_id',
        'notify_party_contact_id',
        'bank_contact_id',
        'extracted_fields',
        'field_mappings',
        'user_provided_data',
        'generated_file_path',
        'output_format',
        'status',
        'error_message',
    ];

    protected $casts = [
        'extracted_fields' => 'array',
        'field_mappings' => 'array',
        'user_provided_data' => 'array',
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
                    $builder->where('filled_declaration_forms.organization_id', $user->organization_id);
                } elseif ($user->is_individual) {
                    $builder->where('filled_declaration_forms.user_id', $user->id);
                }
            }
        });
    }

    /**
     * Get output format options
     */
    public static function getOutputFormats(): array
    {
        return [
            self::OUTPUT_WEB => 'Web Preview / Print',
            self::OUTPUT_PDF_OVERLAY => 'PDF with Data Overlay',
            self::OUTPUT_PDF_FILLABLE => 'Fillable PDF',
            self::OUTPUT_DATA_ONLY => 'Data Mapping Only',
        ];
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETE => 'Complete',
            self::STATUS_ERROR => 'Error',
            default => 'Unknown',
        };
    }

    /**
     * Get output format label
     */
    public function getOutputFormatLabelAttribute(): string
    {
        return self::getOutputFormats()[$this->output_format] ?? 'Unknown';
    }

    // ==========================================
    // Relationships
    // ==========================================

    public function declarationForm()
    {
        return $this->belongsTo(DeclarationForm::class);
    }

    public function template()
    {
        return $this->belongsTo(CountryFormTemplate::class, 'country_form_template_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shipperContact()
    {
        return $this->belongsTo(TradeContact::class, 'shipper_contact_id');
    }

    public function consigneeContact()
    {
        return $this->belongsTo(TradeContact::class, 'consignee_contact_id');
    }

    public function brokerContact()
    {
        return $this->belongsTo(TradeContact::class, 'broker_contact_id');
    }

    public function notifyPartyContact()
    {
        return $this->belongsTo(TradeContact::class, 'notify_party_contact_id');
    }

    public function bankContact()
    {
        return $this->belongsTo(TradeContact::class, 'bank_contact_id');
    }

    /**
     * Get the country through the template
     */
    public function country()
    {
        return $this->hasOneThrough(
            Country::class,
            CountryFormTemplate::class,
            'id', // Foreign key on country_form_templates table
            'id', // Foreign key on countries table
            'country_form_template_id', // Local key on filled_declaration_forms table
            'country_id' // Local key on country_form_templates table
        );
    }

    /**
     * Get country ID attribute (accessor for convenience)
     */
    public function getCountryIdAttribute()
    {
        return $this->template?->country_id;
    }

    // ==========================================
    // Scopes
    // ==========================================

    public function scopeComplete($query)
    {
        return $query->where('status', self::STATUS_COMPLETE);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    // ==========================================
    // Methods
    // ==========================================

    /**
     * Get all field values (merged from all sources)
     */
    public function getAllFieldValues(): array
    {
        return $this->field_mappings ?? [];
    }

    /**
     * Get a specific field value
     */
    public function getFieldValue(string $fieldName): mixed
    {
        return $this->field_mappings[$fieldName] ?? null;
    }

    /**
     * Set a field value
     */
    public function setFieldValue(string $fieldName, mixed $value): void
    {
        $mappings = $this->field_mappings ?? [];
        $mappings[$fieldName] = $value;
        $this->field_mappings = $mappings;
    }

    /**
     * Get missing required fields
     */
    public function getMissingRequiredFields(): array
    {
        $extractedFields = $this->extracted_fields ?? [];
        $fieldValues = $this->field_mappings ?? [];
        
        $missing = [];
        
        foreach ($extractedFields as $field) {
            if (($field['is_required'] ?? false) && empty($fieldValues[$field['field_name']] ?? null)) {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }

    /**
     * Check if all required fields are filled
     */
    public function isComplete(): bool
    {
        return empty($this->getMissingRequiredFields());
    }

    /**
     * Mark as complete
     */
    public function markComplete(string $generatedFilePath = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETE,
            'generated_file_path' => $generatedFilePath,
        ]);
    }

    /**
     * Mark as error
     */
    public function markError(string $message): void
    {
        $this->update([
            'status' => self::STATUS_ERROR,
            'error_message' => $message,
        ]);
    }
}
