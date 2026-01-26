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
            
            // Admin users have access to everything - no tenant restriction
            if ($user->isAdmin()) {
                $request->attributes->set('tenant_type', 'admin');
                $request->attributes->set('tenant_id', null);
                return $next($request);
            }
            
            // Agent users have multi-tenant context
            if ($user->isAgent()) {
                $request->attributes->set('tenant_type', 'agent');
                $request->attributes->set('tenant_id', $user->id);
                
                // Get the active client org IDs for easy access
                $clientOrgIds = $user->getAgentClientIds();
                $request->attributes->set('agent_client_ids', $clientOrgIds);
                
                // Check if agent has selected a specific client context
                $selectedClientId = session('agent_selected_client_id');
                if ($selectedClientId && in_array($selectedClientId, $clientOrgIds)) {
                    $request->attributes->set('agent_current_client_id', $selectedClientId);
                }
                
                return $next($request);
            }
            
            // Regular organization users
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
