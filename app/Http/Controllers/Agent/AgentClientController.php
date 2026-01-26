<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\DeclarationForm;
use App\Models\Organization;
use Illuminate\Http\Request;

class AgentClientController extends Controller
{
    /**
     * Display list of agent's clients
     */
    public function index()
    {
        $user = auth()->user();
        $clients = $user->activeAgentClients()
            ->with('country')
            ->get();

        // Get stats for each client
        $clientStats = [];
        foreach ($clients as $client) {
            $clientStats[$client->id] = [
                'total_declarations' => DeclarationForm::withoutGlobalScope('tenant')
                    ->where('organization_id', $client->id)
                    ->count(),
                'pending_submissions' => DeclarationForm::withoutGlobalScope('tenant')
                    ->where('organization_id', $client->id)
                    ->whereIn('submission_status', [
                        DeclarationForm::SUBMISSION_STATUS_DRAFT,
                        DeclarationForm::SUBMISSION_STATUS_READY,
                    ])
                    ->count(),
                'submitted' => DeclarationForm::withoutGlobalScope('tenant')
                    ->where('organization_id', $client->id)
                    ->where('submission_status', DeclarationForm::SUBMISSION_STATUS_SUBMITTED)
                    ->count(),
            ];
        }

        return view('agent.clients.index', compact('clients', 'clientStats'));
    }

    /**
     * Display a specific client's details
     */
    public function show(Organization $organization)
    {
        $user = auth()->user();
        
        // Verify agent has access to this organization
        if (!$user->hasAccessToOrganization($organization->id)) {
            abort(403, 'You do not have access to this organization.');
        }

        $organization->load('country');

        // Get client statistics
        $stats = [
            'total_declarations' => DeclarationForm::withoutGlobalScope('tenant')
                ->where('organization_id', $organization->id)
                ->count(),
            'by_status' => [],
        ];

        foreach (DeclarationForm::getSubmissionStatuses() as $status => $label) {
            $stats['by_status'][$status] = [
                'label' => $label,
                'count' => DeclarationForm::withoutGlobalScope('tenant')
                    ->where('organization_id', $organization->id)
                    ->where('submission_status', $status)
                    ->count(),
            ];
        }

        // Get recent declarations for this client
        $recentDeclarations = DeclarationForm::withoutGlobalScope('tenant')
            ->where('organization_id', $organization->id)
            ->with('country')
            ->latest()
            ->take(10)
            ->get();

        return view('agent.clients.show', compact('organization', 'stats', 'recentDeclarations'));
    }
}
