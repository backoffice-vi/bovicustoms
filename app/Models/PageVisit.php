<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class PageVisit extends Model
{
    protected $fillable = [
        'session_id',
        'visitor_id',
        'user_id',
        'ip_address',
        'url',
        'route_name',
        'method',
        'referrer',
        'user_agent',
        'device_type',
        'browser',
        'browser_version',
        'platform',
        'country',
        'country_code',
        'city',
        'region',
        'latitude',
        'longitude',
        'is_bot',
        'response_time_ms',
    ];

    protected $casts = [
        'is_bot' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    /**
     * Get the user that made this visit (if authenticated)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter out bots
     */
    public function scopeHuman($query)
    {
        return $query->where('is_bot', false);
    }

    /**
     * Scope to include only bots
     */
    public function scopeBots($query)
    {
        return $query->where('is_bot', true);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Scope to filter by today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', Carbon::today());
    }

    /**
     * Scope to filter by this week
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ]);
    }

    /**
     * Scope to filter by this month
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', Carbon::now()->month)
                     ->whereYear('created_at', Carbon::now()->year);
    }

    /**
     * Get unique visitors count for a query
     */
    public static function uniqueVisitors($query = null)
    {
        $q = $query ?? static::query();
        return $q->distinct('visitor_id')->count('visitor_id');
    }

    /**
     * Get page view statistics
     */
    public static function getStats($period = 'today')
    {
        $query = static::human();

        switch ($period) {
            case 'today':
                $query->today();
                break;
            case 'week':
                $query->thisWeek();
                break;
            case 'month':
                $query->thisMonth();
                break;
            case '7days':
                $query->where('created_at', '>=', Carbon::now()->subDays(7));
                break;
            case '30days':
                $query->where('created_at', '>=', Carbon::now()->subDays(30));
                break;
        }

        return [
            'page_views' => (clone $query)->count(),
            'unique_visitors' => (clone $query)->distinct('visitor_id')->count('visitor_id'),
            'unique_sessions' => (clone $query)->distinct('session_id')->count('session_id'),
        ];
    }
}
