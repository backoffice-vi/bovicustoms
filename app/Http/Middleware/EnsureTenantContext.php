<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
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
            
            // Set tenant context in request
            if ($user->organization_id) {
                $request->attributes->set('tenant_type', 'organization');
                $request->attributes->set('tenant_id', $user->organization_id);
            } else if ($user->is_individual) {
                $request->attributes->set('tenant_type', 'individual');
                $request->attributes->set('tenant_id', $user->id);
            }
        }
        
        return $next($request);
    }
}
