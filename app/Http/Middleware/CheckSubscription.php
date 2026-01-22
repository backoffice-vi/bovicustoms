<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            
            // Individual users don't have organizations, so skip org subscription check
            if ($user->is_individual) {
                return $next($request);
            }
            
            // Check organization subscription
            if ($user->organization_id) {
                $organization = $user->organization;
                
                if (!$organization->isSubscriptionActive()) {
                    return redirect()->route('subscription.expired')
                        ->with('error', 'Your subscription has expired. Please renew to continue using the platform.');
                }
            }
        }
        
        return $next($request);
    }
}
