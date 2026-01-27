<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicClassificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'search_term',
        'result_code',
        'result_description',
        'duty_rate',
        'confidence',
        'vector_score',
        'success',
        'error_message',
        'ip_address',
        'user_agent',
        'source',
    ];

    protected $casts = [
        'duty_rate' => 'decimal:2',
        'vector_score' => 'decimal:4',
        'confidence' => 'integer',
        'success' => 'boolean',
    ];

    /**
     * Scope for successful classifications
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope for failed classifications
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Get popular search terms
     */
    public static function popularSearchTerms(int $limit = 20)
    {
        return static::select('search_term')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('search_term')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }
}
