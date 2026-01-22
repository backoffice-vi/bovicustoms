<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomsCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'code',
        'hs_code_version',
        'description',
        'duty_rate',
    ];

    protected $casts = [
        'duty_rate' => 'decimal:2',
    ];

    /**
     * Fields to track for history
     */
    protected static array $trackableFields = ['code', 'description', 'duty_rate', 'hs_code_version'];

    /**
     * Current law document ID for tracking (set externally before update)
     */
    public ?int $trackingLawDocumentId = null;

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Track changes on update (when not coming from LawDocumentProcessor)
        static::updating(function (CustomsCode $model) {
            // Skip if tracking is handled externally (e.g., by LawDocumentProcessor)
            if ($model->trackingLawDocumentId !== null) {
                return;
            }

            // Log changes for each tracked field
            foreach (static::$trackableFields as $field) {
                if ($model->isDirty($field)) {
                    CustomsCodeHistory::logChange(
                        $model->id,
                        $field,
                        $model->getOriginal($field),
                        $model->$field,
                        null, // No law document
                        auth()->id()
                    );
                }
            }
        });
    }

    /**
     * Get the country this code belongs to
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the history entries for this code
     */
    public function history()
    {
        return $this->hasMany(CustomsCodeHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Scope to filter by country
     */
    public function scopeForCountry($query, $countryId)
    {
        return $query->where('country_id', $countryId);
    }

    /**
     * Scope to search codes by description or code
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('code', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
    }

    /**
     * Update with history tracking from a law document
     */
    public function updateWithHistory(array $data, ?int $lawDocumentId = null): bool
    {
        // Track changes for each field
        foreach (static::$trackableFields as $field) {
            if (isset($data[$field]) && $this->$field != $data[$field]) {
                CustomsCodeHistory::logChange(
                    $this->id,
                    $field,
                    $this->$field,
                    $data[$field],
                    $lawDocumentId,
                    auth()->id()
                );
            }
        }

        // Mark that tracking is handled externally
        $this->trackingLawDocumentId = $lawDocumentId;
        
        return $this->update($data);
    }
}
