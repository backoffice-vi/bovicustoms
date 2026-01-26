@extends('layouts.app')

@section('title', 'My Clients - Agent Portal')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-building me-2"></i>My Clients</h2>
        <a href="{{ route('agent.dashboard') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        @forelse($clients as $client)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-building me-2 text-primary"></i>
                            {{ $client->name }}
                        </h5>
                        <p class="card-text text-muted">
                            <i class="fas fa-globe me-1"></i>{{ $client->country->name ?? 'N/A' }}
                        </p>
                        
                        <hr>
                        
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="fs-4 fw-bold text-primary">{{ $clientStats[$client->id]['total_declarations'] }}</div>
                                <small class="text-muted">Total</small>
                            </div>
                            <div class="col-4">
                                <div class="fs-4 fw-bold text-warning">{{ $clientStats[$client->id]['pending_submissions'] }}</div>
                                <small class="text-muted">Pending</small>
                            </div>
                            <div class="col-4">
                                <div class="fs-4 fw-bold text-success">{{ $clientStats[$client->id]['submitted'] }}</div>
                                <small class="text-muted">Submitted</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="d-flex gap-2">
                            <a href="{{ route('agent.switch-client', $client) }}" class="btn btn-primary flex-fill">
                                <i class="fas fa-exchange-alt me-1"></i>Switch
                            </a>
                            <a href="{{ route('agent.clients.show', $client) }}" class="btn btn-outline-secondary">
                                <i class="fas fa-info-circle"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-building fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No Clients Assigned</h5>
                        <p class="text-muted">You don't have any active client organizations assigned yet.</p>
                        <p class="text-muted">Please contact an administrator to get assigned to client organizations.</p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
