<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WaitlistSignup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class WaitlistController extends Controller
{
    /**
     * Display a listing of waitlist signups
     */
    public function index(Request $request)
    {
        $query = WaitlistSignup::orderBy('created_at', 'desc');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('comments', 'like', "%{$search}%");
            });
        }

        // Source filter
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        // Date filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $signups = $query->paginate(25);
        
        // Get unique sources for filter dropdown
        $sources = WaitlistSignup::select('source')
            ->distinct()
            ->whereNotNull('source')
            ->pluck('source');

        // Stats
        $stats = [
            'total' => WaitlistSignup::count(),
            'today' => WaitlistSignup::whereDate('created_at', today())->count(),
            'this_week' => WaitlistSignup::where('created_at', '>=', now()->startOfWeek())->count(),
            'this_month' => WaitlistSignup::where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        return view('admin.waitlist.index', compact('signups', 'sources', 'stats'));
    }

    /**
     * Display the specified signup
     */
    public function show(WaitlistSignup $signup)
    {
        return view('admin.waitlist.show', compact('signup'));
    }

    /**
     * Export waitlist to CSV
     */
    public function export(Request $request)
    {
        $query = WaitlistSignup::orderBy('created_at', 'desc');

        // Apply same filters as index
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('comments', 'like', "%{$search}%");
            });
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $signups = $query->get();

        $csvContent = "Email,Source,Comments,Interested Features,Signed Up At\n";
        
        foreach ($signups as $signup) {
            $features = is_array($signup->interested_features) 
                ? implode('; ', $signup->interested_features) 
                : '';
            $comments = str_replace(['"', "\n", "\r"], ['""', ' ', ' '], $signup->comments ?? '');
            
            $csvContent .= sprintf(
                '"%s","%s","%s","%s","%s"' . "\n",
                $signup->email,
                $signup->source ?? '',
                $comments,
                $features,
                $signup->created_at->format('Y-m-d H:i:s')
            );
        }

        $filename = 'waitlist-export-' . date('Y-m-d') . '.csv';

        return Response::make($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    /**
     * Remove the specified signup
     */
    public function destroy(WaitlistSignup $signup)
    {
        $signup->delete();

        return redirect()->route('admin.waitlist.index')
            ->with('success', 'Signup removed from waitlist.');
    }
}
