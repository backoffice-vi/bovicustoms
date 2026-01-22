<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && !auth()->user()->onboarding_completed) {
            // Allow access to onboarding routes
            if ($request->routeIs('onboarding.*') || $request->routeIs('logout')) {
                return $next($request);
            }
            
            return redirect()->route('onboarding.index');
        }
        
        return $next($request);
    }
}
