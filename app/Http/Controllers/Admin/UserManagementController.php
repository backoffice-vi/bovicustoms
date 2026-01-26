<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Organization;
use App\Models\AgentOrganizationClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        $query = User::with('organization')->orderBy('created_at', 'desc');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        $organizations = Organization::orderBy('name')->get();
        return view('admin.users.create', compact('organizations'));
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,user,agent',
            'organization_id' => 'nullable|exists:organizations,id',
            'is_individual' => 'required|boolean',
            // Agent-specific fields
            'agent_license_number' => 'nullable|string|max:100',
            'agent_company_name' => 'nullable|string|max:255',
            'agent_address' => 'nullable|string|max:500',
            'agent_phone' => 'nullable|string|max:50',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['onboarding_completed'] = true;

        // Clear organization for agents
        if ($validated['role'] === 'agent') {
            $validated['organization_id'] = null;
            $validated['is_individual'] = false;
        }

        $user = User::create($validated);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Display the specified user
     */
    public function show(User $user)
    {
        $user->load(['organization', 'invoices' => function($query) {
            $query->latest()->limit(10);
        }]);

        // Load agent clients if user is an agent
        $agentClients = [];
        $availableOrganizations = [];
        if ($user->isAgent()) {
            $agentClients = AgentOrganizationClient::where('agent_user_id', $user->id)
                ->with('organization')
                ->get();
            
            $assignedOrgIds = $agentClients->pluck('organization_id')->toArray();
            $availableOrganizations = Organization::whereNotIn('id', $assignedOrgIds)
                ->orderBy('name')
                ->get();
        }

        return view('admin.users.show', compact('user', 'agentClients', 'availableOrganizations'));
    }

    /**
     * Show the form for editing the specified user
     */
    public function edit(User $user)
    {
        $organizations = Organization::orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'organizations'));
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => 'required|in:admin,user,agent',
            'organization_id' => 'nullable|exists:organizations,id',
            'is_individual' => 'required|boolean',
            // Agent-specific fields
            'agent_license_number' => 'nullable|string|max:100',
            'agent_company_name' => 'nullable|string|max:255',
            'agent_address' => 'nullable|string|max:500',
            'agent_phone' => 'nullable|string|max:50',
        ]);

        // Only update password if provided
        if ($request->filled('password')) {
            $request->validate([
                'password' => 'required|string|min:8|confirmed',
            ]);
            $validated['password'] = Hash::make($request->password);
        }

        // Clear organization for agents
        if ($validated['role'] === 'agent') {
            $validated['organization_id'] = null;
            $validated['is_individual'] = false;
        } else {
            // Clear agent fields for non-agents
            $validated['agent_license_number'] = null;
            $validated['agent_company_name'] = null;
            $validated['agent_address'] = null;
            $validated['agent_phone'] = null;
        }

        $user->update($validated);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Assign a client organization to an agent
     */
    public function assignClient(Request $request, User $user)
    {
        if (!$user->isAgent()) {
            return redirect()->back()->with('error', 'User is not an agent.');
        }

        $validated = $request->validate([
            'organization_id' => 'required|exists:organizations,id',
            'notes' => 'nullable|string|max:500',
        ]);

        // Check if already assigned
        $exists = AgentOrganizationClient::where('agent_user_id', $user->id)
            ->where('organization_id', $validated['organization_id'])
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'Client already assigned to this agent.');
        }

        AgentOrganizationClient::create([
            'agent_user_id' => $user->id,
            'organization_id' => $validated['organization_id'],
            'status' => AgentOrganizationClient::STATUS_ACTIVE,
            'authorized_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Client assigned to agent successfully.');
    }

    /**
     * Remove a client from an agent
     */
    public function removeClient(User $user, Organization $organization)
    {
        if (!$user->isAgent()) {
            return redirect()->back()->with('error', 'User is not an agent.');
        }

        AgentOrganizationClient::where('agent_user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->delete();

        return redirect()->back()->with('success', 'Client removed from agent.');
    }

    /**
     * Toggle client status (active/revoked)
     */
    public function toggleClientStatus(User $user, Organization $organization)
    {
        if (!$user->isAgent()) {
            return redirect()->back()->with('error', 'User is not an agent.');
        }

        $client = AgentOrganizationClient::where('agent_user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$client) {
            return redirect()->back()->with('error', 'Client relationship not found.');
        }

        if ($client->isActive()) {
            $client->revoke();
            $message = 'Client access revoked.';
        } else {
            $client->activate();
            $message = 'Client access activated.';
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user)
    {
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }
}
