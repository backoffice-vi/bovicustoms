@extends('layouts.app')

@section('title', 'View User - Admin')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>User Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Name</label>
                        <p class="mb-0"><strong>{{ $user->name }}</strong></p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Email</label>
                        <p class="mb-0">{{ $user->email }}</p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Role</label>
                        <p class="mb-0">
                            @if($user->role === 'admin')
                                <span class="badge bg-danger">Admin</span>
                            @elseif($user->role === 'agent')
                                <span class="badge bg-primary">Agent</span>
                            @else
                                <span class="badge bg-secondary">User</span>
                            @endif
                        </p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Account Type</label>
                        <p class="mb-0">
                            @if($user->is_individual)
                                <span class="badge bg-info">Individual</span>
                            @else
                                <span class="badge bg-success">Organization</span>
                            @endif
                        </p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Organization</label>
                        <p class="mb-0">
                            @if($user->organization)
                                <i class="fas fa-building me-1"></i>{{ $user->organization->name }}
                            @else
                                <span class="text-muted">None</span>
                            @endif
                        </p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Onboarding</label>
                        <p class="mb-0">
                            @if($user->onboarding_completed)
                                <span class="badge bg-success">Completed</span>
                            @else
                                <span class="badge bg-warning">Pending</span>
                            @endif
                        </p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Created</label>
                        <p class="mb-0">{{ $user->created_at->format('M d, Y h:i A') }}</p>
                    </div>

                    <div class="mb-0">
                        <label class="text-muted small">Last Updated</label>
                        <p class="mb-0">{{ $user->updated_at->format('M d, Y h:i A') }}</p>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary btn-sm w-100 mb-2">
                        <i class="fas fa-edit me-1"></i> Edit User
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="fas fa-arrow-left me-1"></i> Back to Users
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            @if($user->isAgent())
            {{-- Agent-specific sections --}}
            
            {{-- Agent Details Card --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Agent Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl>
                                <dt>Company Name</dt>
                                <dd>{{ $user->agent_company_name ?? 'Not specified' }}</dd>
                                
                                <dt>License Number</dt>
                                <dd>{{ $user->agent_license_number ?? 'Not specified' }}</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl>
                                <dt>Phone</dt>
                                <dd>{{ $user->agent_phone ?? 'Not specified' }}</dd>
                                
                                <dt>Address</dt>
                                <dd>{{ $user->agent_address ?? 'Not specified' }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Client Organizations Card --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-building me-2"></i>Client Organizations</h5>
                    <span class="badge bg-primary">{{ count($agentClients) }} clients</span>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    {{-- Add Client Form --}}
                    @if(count($availableOrganizations) > 0)
                    <form action="{{ route('admin.users.assign-client', $user) }}" method="POST" class="mb-4">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-6">
                                <select name="organization_id" class="form-select" required>
                                    <option value="">Select organization to add...</option>
                                    @foreach($availableOrganizations as $org)
                                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="notes" class="form-control" placeholder="Notes (optional)">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-plus"></i> Add
                                </button>
                            </div>
                        </div>
                    </form>
                    @endif

                    {{-- Current Clients List --}}
                    @if(count($agentClients) > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Organization</th>
                                        <th>Status</th>
                                        <th>Authorized</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($agentClients as $client)
                                        <tr>
                                            <td>
                                                <strong>{{ $client->organization->name }}</strong>
                                                <br><small class="text-muted">{{ $client->organization->country->name ?? 'N/A' }}</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $client->status_color }}">
                                                    {{ $client->status_label }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($client->authorized_at)
                                                    {{ $client->authorized_at->format('M d, Y') }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ Str::limit($client->notes, 30) ?? '-' }}</td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <form action="{{ route('admin.users.toggle-client', [$user, $client->organization]) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn-outline-{{ $client->isActive() ? 'warning' : 'success' }}" 
                                                                title="{{ $client->isActive() ? 'Revoke' : 'Activate' }}">
                                                            <i class="fas fa-{{ $client->isActive() ? 'ban' : 'check' }}"></i>
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('admin.users.remove-client', [$user, $client->organization]) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger" title="Remove" 
                                                                onclick="return confirm('Remove this client from the agent?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-building fa-3x mb-3 d-block"></i>
                            <p>No clients assigned yet.</p>
                            <p class="small">Use the form above to assign client organizations to this agent.</p>
                        </div>
                    @endif
                </div>
            </div>
            @else
            {{-- Regular user sections --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Recent Invoices</h5>
                </div>
                <div class="card-body">
                    @if($user->invoices->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Status</th>
                                        <th>Items</th>
                                        <th>Total Value</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($user->invoices as $invoice)
                                        <tr>
                                            <td><strong>{{ $invoice->invoice_number }}</strong></td>
                                            <td>
                                                @if($invoice->status === 'draft')
                                                    <span class="badge bg-secondary">Draft</span>
                                                @elseif($invoice->status === 'pending_classification')
                                                    <span class="badge bg-warning">Pending</span>
                                                @elseif($invoice->status === 'classified')
                                                    <span class="badge bg-info">Classified</span>
                                                @else
                                                    <span class="badge bg-success">Complete</span>
                                                @endif
                                            </td>
                                            <td>{{ $invoice->items->count() }}</td>
                                            <td>${{ number_format($invoice->total_value, 2) }}</td>
                                            <td>{{ $invoice->created_at->format('M d, Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-file-invoice fa-3x mb-3 d-block"></i>
                            <p>No invoices yet.</p>
                        </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
