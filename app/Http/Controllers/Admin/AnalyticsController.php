<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageVisit;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Display the analytics dashboard
     */
    public function index(Request $request)
    {
        $period = $request->get('period', '7days');
        $startDate = $this->getStartDate($period);
        $endDate = Carbon::now();

        // Check which database driver we're using
        $driver = DB::connection()->getDriverName();

        // Base query for human visits only
        $baseQuery = PageVisit::human()->where('created_at', '>=', $startDate);

        // Overview stats
        $stats = [
            'total_page_views' => (clone $baseQuery)->count(),
            'unique_visitors' => (clone $baseQuery)->distinct('visitor_id')->count('visitor_id'),
            'unique_sessions' => (clone $baseQuery)->distinct('session_id')->count('session_id'),
            'avg_response_time' => round((clone $baseQuery)->avg('response_time_ms') ?? 0),
            'bot_visits' => PageVisit::bots()->where('created_at', '>=', $startDate)->count(),
        ];

        // Calculate returning visitors
        $returningVisitorIds = PageVisit::human()
            ->where('created_at', '>=', $startDate)
            ->select('visitor_id')
            ->groupBy('visitor_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('visitor_id');
        
        $stats['returning_visitors'] = $returningVisitorIds->count();
        $stats['new_visitors'] = $stats['unique_visitors'] - $stats['returning_visitors'];

        // Page views by day - database agnostic
        $dateExpr = $driver === 'sqlite' 
            ? "strftime('%Y-%m-%d', created_at)" 
            : 'DATE(created_at)';
            
        $pageViewsByDay = PageVisit::human()
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw("{$dateExpr} as date"),
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT visitor_id) as visitors')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top pages
        $topPages = PageVisit::human()
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('route_name')
            ->select('route_name', 'url', DB::raw('COUNT(*) as views'))
            ->groupBy('route_name', 'url')
            ->orderByDesc('views')
            ->limit(10)
            ->get();

        // Traffic by country
        $trafficByCountry = PageVisit::human()
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('country')
            ->select('country', 'country_code', DB::raw('COUNT(*) as visits'), DB::raw('COUNT(DISTINCT visitor_id) as visitors'))
            ->groupBy('country', 'country_code')
            ->orderByDesc('visits')
            ->limit(10)
            ->get();

        // Traffic by device type
        $trafficByDevice = PageVisit::human()
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('device_type')
            ->select('device_type', DB::raw('COUNT(*) as visits'))
            ->groupBy('device_type')
            ->orderByDesc('visits')
            ->get();

        // Traffic by browser
        $trafficByBrowser = PageVisit::human()
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('browser')
            ->select('browser', DB::raw('COUNT(*) as visits'))
            ->groupBy('browser')
            ->orderByDesc('visits')
            ->limit(5)
            ->get();

        // Traffic by platform (OS)
        $trafficByPlatform = PageVisit::human()
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('platform')
            ->select('platform', DB::raw('COUNT(*) as visits'))
            ->groupBy('platform')
            ->orderByDesc('visits')
            ->limit(5)
            ->get();

        // Hourly traffic (for today) - database agnostic
        $hourExpr = $driver === 'sqlite'
            ? "CAST(strftime('%H', created_at) AS INTEGER)"
            : 'HOUR(created_at)';
            
        $todayDateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', created_at) = strftime('%Y-%m-%d', 'now', 'localtime')"
            : 'DATE(created_at) = CURDATE()';
            
        $hourlyTraffic = PageVisit::human()
            ->whereRaw($todayDateExpr)
            ->select(
                DB::raw("{$hourExpr} as hour"),
                DB::raw('COUNT(*) as visits')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');

        // Fill in missing hours
        $hourlyData = [];
        for ($i = 0; $i < 24; $i++) {
            $hourlyData[$i] = $hourlyTraffic->get($i)?->visits ?? 0;
        }

        // Top referrers
        $topReferrers = PageVisit::human()
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('referrer')
            ->where('referrer', 'not like', '%' . request()->getHost() . '%')
            ->select('referrer', DB::raw('COUNT(*) as visits'))
            ->groupBy('referrer')
            ->orderByDesc('visits')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $parsed = parse_url($item->referrer);
                $item->domain = $parsed['host'] ?? $item->referrer;
                return $item;
            });

        // Recent visits (live feed)
        $recentVisits = PageVisit::with('user')
            ->human()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('admin.analytics.index', compact(
            'stats',
            'period',
            'startDate',
            'endDate',
            'pageViewsByDay',
            'topPages',
            'trafficByCountry',
            'trafficByDevice',
            'trafficByBrowser',
            'trafficByPlatform',
            'hourlyData',
            'topReferrers',
            'recentVisits'
        ));
    }

    /**
     * Get start date based on period
     */
    protected function getStartDate(string $period): Carbon
    {
        return match ($period) {
            'today' => Carbon::today(),
            'yesterday' => Carbon::yesterday(),
            '7days' => Carbon::now()->subDays(7),
            '30days' => Carbon::now()->subDays(30),
            '90days' => Carbon::now()->subDays(90),
            'year' => Carbon::now()->subYear(),
            default => Carbon::now()->subDays(7),
        };
    }

    /**
     * Export analytics data as CSV
     */
    public function export(Request $request)
    {
        $period = $request->get('period', '7days');
        $startDate = $this->getStartDate($period);

        $visits = PageVisit::human()
            ->where('created_at', '>=', $startDate)
            ->orderByDesc('created_at')
            ->get();

        $filename = 'analytics_export_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($visits) {
            $file = fopen('php://output', 'w');
            
            // Header row
            fputcsv($file, [
                'Date',
                'Time',
                'URL',
                'Route',
                'Country',
                'City',
                'Device',
                'Browser',
                'Platform',
                'Referrer',
                'User',
            ]);

            foreach ($visits as $visit) {
                fputcsv($file, [
                    $visit->created_at->format('Y-m-d'),
                    $visit->created_at->format('H:i:s'),
                    $visit->url,
                    $visit->route_name,
                    $visit->country,
                    $visit->city,
                    $visit->device_type,
                    $visit->browser,
                    $visit->platform,
                    $visit->referrer,
                    $visit->user?->name ?? 'Guest',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
