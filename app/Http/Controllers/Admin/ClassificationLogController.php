<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicClassificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class ClassificationLogController extends Controller
{
    /**
     * Display classification logs with analytics
     */
    public function index(Request $request)
    {
        $query = PublicClassificationLog::orderBy('created_at', 'desc');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('search_term', 'like', "%{$search}%")
                  ->orWhere('result_code', 'like', "%{$search}%")
                  ->orWhere('result_description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('success', $request->status === 'success');
        }

        // Date filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(25);

        // Stats
        $stats = [
            'total' => PublicClassificationLog::count(),
            'today' => PublicClassificationLog::whereDate('created_at', today())->count(),
            'this_week' => PublicClassificationLog::where('created_at', '>=', now()->startOfWeek())->count(),
            'success_rate' => PublicClassificationLog::count() > 0 
                ? round(PublicClassificationLog::successful()->count() / PublicClassificationLog::count() * 100, 1)
                : 0,
        ];

        // Popular searches (top 10)
        $popularSearches = PublicClassificationLog::select('search_term')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('search_term')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Popular results (top 10 HS codes)
        $popularCodes = PublicClassificationLog::select('result_code', 'result_description')
            ->selectRaw('COUNT(*) as count')
            ->whereNotNull('result_code')
            ->groupBy('result_code', 'result_description')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return view('admin.classification-logs.index', compact(
            'logs', 
            'stats', 
            'popularSearches',
            'popularCodes'
        ));
    }

    /**
     * Export logs to CSV
     */
    public function export(Request $request)
    {
        $query = PublicClassificationLog::orderBy('created_at', 'desc');

        // Apply same filters as index
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('search_term', 'like', "%{$search}%")
                  ->orWhere('result_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('success', $request->status === 'success');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->get();

        $csvContent = "Search Term,Result Code,Description,Duty Rate,Confidence,Success,IP Address,Date\n";
        
        foreach ($logs as $log) {
            $searchTerm = str_replace(['"', "\n", "\r"], ['""', ' ', ' '], $log->search_term);
            $description = str_replace(['"', "\n", "\r"], ['""', ' ', ' '], $log->result_description ?? '');
            
            $csvContent .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $searchTerm,
                $log->result_code ?? '',
                $description,
                $log->duty_rate ?? '',
                $log->confidence ?? '',
                $log->success ? 'Yes' : 'No',
                $log->ip_address ?? '',
                $log->created_at->format('Y-m-d H:i:s')
            );
        }

        $filename = 'classification-logs-' . date('Y-m-d') . '.csv';

        return Response::make($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }
}
