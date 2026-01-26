<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\DeclarationForm;
use App\Models\Organization;
use Illuminate\Http\Request;

class AgentDashboardController extends Controller
{
    /**
     * Display the agent dashboard
     */
    public function index()
    {
        $user = auth()->user();
        $clients = $user->activeAgentClients()->with('country')->get();
        
        // Get statistics across all clients
        $clientIds = $clients->pluck('id')->toArray();
        
        $stats = [
            'total_clients' => $clients->count(),
            'total_declarations' => DeclarationForm::withoutGlobalScope('tenant')
                ->whereIn('organization_id', $clientIds)
                ->count(),
            'pending_submissions' => DeclarationForm::withoutGlobalScope('tenant')
                ->whereIn('organization_id', $clientIds)
                ->whereIn('submission_status', [
                    DeclarationForm::SUBMISSION_STATUS_DRAFT,
                    DeclarationForm::SUBMISSION_STATUS_READY,
                ])
                ->count(),
            'submitted_today' => DeclarationForm::withoutGlobalScope('tenant')
                ->whereIn('organization_id', $clientIds)
                ->where('submission_status', DeclarationForm::SUBMISSION_STATUS_SUBMITTED)
                ->whereDate('submitted_at', today())
                ->count(),
        ];

        // Get recent declarations across all clients
        $recentDeclarations = DeclarationForm::withoutGlobalScope('tenant')
            ->whereIn('organization_id', $clientIds)
            ->with(['organization', 'country'])
            ->latest()
            ->take(10)
            ->get();

        // Get the currently selected client (if any)
        $selectedClientId = session('agent_selected_client_id');
        $selectedClient = $selectedClientId ? $clients->find($selectedClientId) : null;

        return view('agent.dashboard', compact(
            'clients',
            'stats',
            'recentDeclarations',
            'selectedClient'
        ));
    }

    /**
     * Switch to a specific client context
     */
    public function switchClient(Request $request, Organization $organization)
    {
        $user = auth()->user();

        // Verify agent has access to this organization
        if (!$user->hasAccessToOrganization($organization->id)) {
            abort(403, 'You do not have access to this organization.');
        }

        // Store selected client in session
        session(['agent_selected_client_id' => $organization->id]);

        return redirect()->back()->with('success', "Switched to {$organization->name}");
    }

    /**
     * Clear client context (view all clients)
     */
    public function clearClientContext()
    {
        session()->forget('agent_selected_client_id');
        
        return redirect()->route('agent.dashboard')->with('success', 'Now viewing all clients');
    }
}
