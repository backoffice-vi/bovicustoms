<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect()->route('login')->with('error', 'Please login to access this page.');
        }

        $user = auth()->user();

        // Check if user has agent role
        if (!$user->isAgent()) {
            abort(403, 'Unauthorized. Agent access required.');
        }

        // Check if agent has any active clients
        if ($user->activeAgentClients()->count() === 0) {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have any active client organizations assigned. Please contact an administrator.');
        }

        return $next($request);
    }
}
