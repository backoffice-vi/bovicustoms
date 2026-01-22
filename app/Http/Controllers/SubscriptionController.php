<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Show subscription management page
     */
    public function index()
    {
        $user = auth()->user();
        $organization = $user->organization;
        $plans = SubscriptionPlan::active()->get();
        
        return view('subscription.index', compact('user', 'organization', 'plans'));
    }

    /**
     * Show subscription expired page
     */
    public function expired()
    {
        return view('subscription.expired');
    }

    /**
     * Upgrade subscription (placeholder for Stripe integration)
     */
    public function upgrade(Request $request)
    {
        $validated = $request->validate([
            'plan_slug' => 'required|exists:subscription_plans,slug',
        ]);

        $user = auth()->user();
        $organization = $user->organization;
        $plan = SubscriptionPlan::where('slug', $validated['plan_slug'])->first();

        if (!$organization) {
            return back()->with('error', 'Only organization accounts can upgrade plans.');
        }

        // TODO: Implement Stripe payment integration here
        // For now, just update the plan
        $organization->subscription_plan = $plan->slug;
        $organization->subscription_status = 'active';
        $organization->invoice_limit = $plan->invoice_limit;
        $organization->save();

        return redirect()->route('subscription.index')
            ->with('success', 'Your subscription has been updated to ' . $plan->name . '!');
    }

    /**
     * Check usage limits
     */
    public function checkUsage()
    {
        $user = auth()->user();
        
        if ($user->is_individual) {
            // Individual users on free tier
            $monthStart = now()->startOfMonth();
            $invoiceCount = $user->invoices()->where('created_at', '>=', $monthStart)->count();
            $limit = 10; // Free tier limit
            
            return response()->json([
                'used' => $invoiceCount,
                'limit' => $limit,
                'remaining' => max(0, $limit - $invoiceCount),
                'percentage' => min(100, ($invoiceCount / $limit) * 100),
            ]);
        }
        
        if ($user->organization) {
            $monthStart = now()->startOfMonth();
            $invoiceCount = $user->organization->invoices()->where('created_at', '>=', $monthStart)->count();
            $limit = $user->organization->invoice_limit;
            
            return response()->json([
                'used' => $invoiceCount,
                'limit' => $limit,
                'remaining' => $limit ? max(0, $limit - $invoiceCount) : 'unlimited',
                'percentage' => $limit ? min(100, ($invoiceCount / $limit) * 100) : 0,
            ]);
        }
        
        return response()->json(['error' => 'Unable to determine usage'], 400);
    }
}
