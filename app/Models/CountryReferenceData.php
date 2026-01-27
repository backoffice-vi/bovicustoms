<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CountryReferenceData extends Model
{
    use HasFactory;

    protected $table = 'country_reference_data';

    protected $fillable = [
        'country_id',
        'reference_type',
        'code',
        'label',
        'local_matches',
        'metadata',
        'is_active',
        'is_default',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'local_matches' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Reference type constants
     */
    const TYPE_CARRIER = 'carrier';
    const TYPE_PORT = 'port';
    const TYPE_COUNTRY = 'country';
    const TYPE_CPC = 'cpc';
    const TYPE_CURRENCY = 'currency';
    const TYPE_UNIT = 'unit';
    const TYPE_TAX_TYPE = 'tax_type';
    const TYPE_EXEMPT_ID = 'exempt_id';
    const TYPE_CHARGE_CODE = 'charge_code';
    const TYPE_ADDITIONAL_INFO = 'additional_info';
    const TYPE_PAYMENT_METHOD = 'payment_method';

    /**
     * Get all valid reference types
     */
    public static function getValidTypes(): array
    {
        return [
            self::TYPE_CARRIER,
            self::TYPE_PORT,
            self::TYPE_COUNTRY,
            self::TYPE_CPC,
            self::TYPE_CURRENCY,
            self::TYPE_UNIT,
            self::TYPE_TAX_TYPE,
            self::TYPE_EXEMPT_ID,
            self::TYPE_CHARGE_CODE,
            self::TYPE_ADDITIONAL_INFO,
            self::TYPE_PAYMENT_METHOD,
        ];
    }

    /**
     * Get the country that owns this reference data
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Scope to filter by reference type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('reference_type', $type);
    }

    /**
     * Scope to only include active records
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get default record for a type
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }

    /**
     * Find a reference by code for a specific country and type
     */
    public static function findByCode(int $countryId, string $type, string $code): ?self
    {
        return static::where('country_id', $countryId)
            ->where('reference_type', $type)
            ->where('code', $code)
            ->first();
    }

    /**
     * Find a reference by matching a local value
     * Searches the local_matches JSON array for a match
     */
    public static function findByLocalMatch(int $countryId, string $type, string $localValue): ?self
    {
        $localValue = trim($localValue);
        $localValueLower = strtolower($localValue);

        // First try exact code match
        $record = static::where('country_id', $countryId)
            ->where('reference_type', $type)
            ->where('code', $localValue)
            ->first();

        if ($record) {
            return $record;
        }

        // Search in local_matches
        $records = static::where('country_id', $countryId)
            ->where('reference_type', $type)
            ->active()
            ->get();

        foreach ($records as $record) {
            if (!$record->local_matches) {
                continue;
            }

            foreach ($record->local_matches as $match) {
                if (strtolower(trim($match)) === $localValueLower) {
                    return $record;
                }
            }
        }

        return null;
    }

    /**
     * Get formatted option for dropdown display
     */
    public function getDropdownOption(): array
    {
        return [
            'value' => $this->code,
            'label' => "{$this->code} - {$this->label}",
            'is_default' => $this->is_default,
        ];
    }

    /**
     * Get all references of a type for a country as dropdown options
     */
    public static function getDropdownOptions(int $countryId, string $type): array
    {
        return static::where('country_id', $countryId)
            ->where('reference_type', $type)
            ->active()
            ->ordered()
            ->get()
            ->map(fn($record) => $record->getDropdownOption())
            ->toArray();
    }

    /**
     * Import reference data from raw text format (CODE NAME format, one per line)
     */
    public static function importFromText(
        int $countryId,
        string $type,
        string $rawText,
        array $customMatches = [],
        ?string $defaultCode = null
    ): int {
        $lines = explode("\n", $rawText);
        $imported = 0;

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $parts = explode(' ', $line, 2);
            $code = $parts[0];
            $name = $parts[1] ?? '';

            // Build local matches array
            $localMatches = [$name, $code];
            
            // Add any custom matches for this code
            if (isset($customMatches[$code])) {
                $localMatches = array_merge($localMatches, (array) $customMatches[$code]);
            }

            static::updateOrCreate(
                [
                    'country_id' => $countryId,
                    'reference_type' => $type,
                    'code' => $code,
                ],
                [
                    'label' => $name,
                    'local_matches' => array_unique($localMatches),
                    'sort_order' => $index,
                    'is_default' => ($defaultCode && $code === $defaultCode),
                    'is_active' => true,
                ]
            );
            $imported++;
        }

        return $imported;
    }
}
