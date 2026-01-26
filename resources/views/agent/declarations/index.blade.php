@extends('layouts.app')

@section('title', 'Declarations - Agent Portal')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-alt me-2"></i>Declarations</h2>
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

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('agent.declarations.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Form #, B/L, Manifest..." 
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Client</label>
                    <select name="client" class="form-select" onchange="window.location.href='{{ route('agent.switch-client', ':id') }}'.replace(':id', this.value)">
                        <option value="">All Clients</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ $selectedClientId == $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="{{ route('agent.declarations.index') }}" class="btn btn-outline-secondary">
                        Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Declarations Table -->
    <div class="card">
        <div class="card-body">
            @if($declarations->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Form Number</th>
                                <th>Client</th>
                                <th>Country</th>
                                <th>CIF Value</th>
                                <th>Total Duty</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($declarations as $declaration)
                                <tr>
                                    <td>
                                        <a href="{{ route('agent.declarations.show', $declaration) }}">
                                            <code>{{ $declaration->form_number }}</code>
                                        </a>
                                        @if($declaration->bill_of_lading_number)
                                            <br><small class="text-muted">B/L: {{ $declaration->bill_of_lading_number }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('agent.clients.show', $declaration->organization) }}">
                                            {{ $declaration->organization->name ?? 'N/A' }}
                                        </a>
                                    </td>
                                    <td>{{ $declaration->country->name ?? 'N/A' }}</td>
                                    <td>${{ number_format($declaration->cif_value ?? 0, 2) }}</td>
                                    <td>${{ number_format($declaration->total_duty ?? 0, 2) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $declaration->submission_status_color }}">
                                            {{ $declaration->submission_status_label }}
                                        </span>
                                        @if($declaration->submitted_at)
                                            <br><small class="text-muted">{{ $declaration->submitted_at->format('M d, Y H:i') }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $declaration->created_at->format('M d, Y') }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('agent.declarations.show', $declaration) }}" 
                                               class="btn btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @if($declaration->canBeSubmitted())
                                                <a href="{{ route('agent.declarations.submit-form', $declaration) }}" 
                                                   class="btn btn-outline-success" title="Submit">
                                                    <i class="fas fa-paper-plane"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $declarations->withQueryString()->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No declarations found</h5>
                    <p class="text-muted">Try adjusting your filters or check back later.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
