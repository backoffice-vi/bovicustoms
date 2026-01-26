@extends('layouts.app')

@section('title', 'Agent Dashboard - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-user-tie me-2"></i>Agent Dashboard</h2>
            <p class="text-muted mb-0">Welcome, {{ auth()->user()->name }}</p>
        </div>
        @if($selectedClient)
        <div class="d-flex align-items-center">
            <span class="badge bg-primary me-2">
                <i class="fas fa-building me-1"></i>{{ $selectedClient->name }}
            </span>
            <a href="{{ route('agent.clear-client') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-times me-1"></i>View All
            </a>
        </div>
        @endif
    </div>

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

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Clients</h6>
                            <h2 class="mb-0">{{ $stats['total_clients'] }}</h2>
                        </div>
                        <i class="fas fa-building fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Declarations</h6>
                            <h2 class="mb-0">{{ $stats['total_declarations'] }}</h2>
                        </div>
                        <i class="fas fa-file-alt fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Pending Submissions</h6>
                            <h2 class="mb-0">{{ $stats['pending_submissions'] }}</h2>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Submitted Today</h6>
                            <h2 class="mb-0">{{ $stats['submitted_today'] }}</h2>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Client Quick Select -->
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-building me-2"></i>My Clients</strong>
                    <a href="{{ route('agent.clients.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    @if($clients->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($clients as $client)
                                <a href="{{ route('agent.switch-client', $client) }}" 
                                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $selectedClient && $selectedClient->id === $client->id ? 'active' : '' }}">
                                    <div>
                                        <strong>{{ $client->name }}</strong>
                                        <br>
                                        <small class="{{ $selectedClient && $selectedClient->id === $client->id ? 'text-white-50' : 'text-muted' }}">
                                            {{ $client->country->name ?? 'N/A' }}
                                        </small>
                                    </div>
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted text-center my-4">No clients assigned yet.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recent Declarations -->
        <div class="col-lg-8 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-file-alt me-2"></i>Recent Declarations</strong>
                    <a href="{{ route('agent.declarations.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    @if($recentDeclarations->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Form #</th>
                                        <th>Client</th>
                                        <th>Country</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentDeclarations as $declaration)
                                        <tr>
                                            <td>
                                                <code>{{ $declaration->form_number }}</code>
                                            </td>
                                            <td>{{ $declaration->organization->name ?? 'N/A' }}</td>
                                            <td>{{ $declaration->country->name ?? 'N/A' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $declaration->submission_status_color }}">
                                                    {{ $declaration->submission_status_label }}
                                                </span>
                                            </td>
                                            <td>{{ $declaration->created_at->format('M d, Y') }}</td>
                                            <td>
                                                <a href="{{ route('agent.declarations.show', $declaration) }}" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted text-center my-4">No declarations found.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <strong><i class="fas fa-bolt me-2"></i>Quick Actions</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <a href="{{ route('agent.declarations.index', ['status' => 'ready']) }}" class="btn btn-lg btn-outline-primary w-100 mb-2">
                        <i class="fas fa-paper-plane me-2"></i>Ready to Submit
                        <span class="badge bg-primary ms-2">{{ $stats['pending_submissions'] }}</span>
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('agent.declarations.index', ['status' => 'submitted']) }}" class="btn btn-lg btn-outline-success w-100 mb-2">
                        <i class="fas fa-check me-2"></i>View Submitted
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('agent.clients.index') }}" class="btn btn-lg btn-outline-info w-100 mb-2">
                        <i class="fas fa-building me-2"></i>Manage Clients
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
