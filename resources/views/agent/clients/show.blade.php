@extends('layouts.app')

@section('title', '{{ $organization->name }} - Agent Portal')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-building me-2"></i>{{ $organization->name }}</h2>
            <p class="text-muted mb-0">
                <i class="fas fa-globe me-1"></i>{{ $organization->country->name ?? 'N/A' }}
            </p>
        </div>
        <div class="btn-group">
            <a href="{{ route('agent.switch-client', $organization) }}" class="btn btn-primary">
                <i class="fas fa-exchange-alt me-2"></i>Switch to Client
            </a>
            <a href="{{ route('agent.clients.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-4 mb-4">
            <!-- Client Stats -->
            <div class="card">
                <div class="card-header">
                    <strong><i class="fas fa-chart-bar me-2"></i>Declaration Statistics</strong>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="fs-1 fw-bold text-primary">{{ $stats['total_declarations'] }}</div>
                        <div class="text-muted">Total Declarations</div>
                    </div>
                    
                    <hr>
                    
                    <h6 class="text-muted mb-3">By Status</h6>
                    @foreach($stats['by_status'] as $status => $data)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>{{ $data['label'] }}</span>
                            <span class="badge bg-secondary">{{ $data['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-8 mb-4">
            <!-- Recent Declarations -->
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-file-alt me-2"></i>Recent Declarations</strong>
                    <a href="{{ route('agent.declarations.index') }}?client={{ $organization->id }}" class="btn btn-sm btn-outline-primary">
                        View All
                    </a>
                </div>
                <div class="card-body">
                    @if($recentDeclarations->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Form #</th>
                                        <th>Country</th>
                                        <th>CIF Value</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentDeclarations as $declaration)
                                        <tr>
                                            <td><code>{{ $declaration->form_number }}</code></td>
                                            <td>{{ $declaration->country->name ?? 'N/A' }}</td>
                                            <td>${{ number_format($declaration->cif_value ?? 0, 2) }}</td>
                                            <td>
                                                <span class="badge bg-{{ $declaration->submission_status_color }}">
                                                    {{ $declaration->submission_status_label }}
                                                </span>
                                            </td>
                                            <td>{{ $declaration->created_at->format('M d') }}</td>
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
                        <p class="text-muted text-center my-4">No declarations found for this client.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
