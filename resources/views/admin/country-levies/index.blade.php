@extends('layouts.app')

@section('title', 'Country Levies - Admin')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-percentage me-2"></i>Country Levies</h2>
            <p class="text-muted mb-0">Configure wharfage, duties, and other standard levies per country</p>
        </div>
        <a href="{{ route('admin.country-levies.create', ['country_id' => $countryId]) }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add Levy
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Country Filter --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Filter by Country</label>
                    <select name="country_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Countries</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" {{ $countryId == $country->id ? 'selected' : '' }}>
                                {{ $country->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    @if($countryId)
                    <a href="{{ route('admin.country-levies.index') }}" class="btn btn-outline-secondary">Clear Filter</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if($levies->count() > 0)
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Country</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th class="text-end">Rate</th>
                            <th>Basis</th>
                            <th>Status</th>
                            <th>Effective</th>
                            <th style="width: 100px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($levies as $levy)
                        <tr>
                            <td>{{ $levy->country?->name ?? '-' }}</td>
                            <td><code>{{ $levy->levy_code }}</code></td>
                            <td>{{ $levy->levy_name }}</td>
                            <td class="text-end">{{ $levy->formatted_rate }}</td>
                            <td>
                                <span class="badge bg-secondary">{{ strtoupper($levy->calculation_basis) }}</span>
                            </td>
                            <td>
                                @if($levy->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                                @if(!$levy->is_currently_effective)
                                    <span class="badge bg-warning text-dark">Not in Effect</span>
                                @endif
                            </td>
                            <td>
                                @if($levy->effective_from || $levy->effective_until)
                                    <small>
                                        {{ $levy->effective_from?->format('M Y') ?? 'Start' }} -
                                        {{ $levy->effective_until?->format('M Y') ?? 'Ongoing' }}
                                    </small>
                                @else
                                    <small class="text-muted">Always</small>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.country-levies.edit', $levy) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.country-levies.destroy', $levy) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete this levy?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($levies->hasPages())
    <div class="mt-4">
        {{ $levies->appends(['country_id' => $countryId])->links() }}
    </div>
    @endif
    @else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-percentage fa-4x text-muted mb-3"></i>
            <h4>No Levies Configured</h4>
            <p class="text-muted mb-4">
                @if($countryId)
                    No levies found for this country.
                @else
                    Configure standard levies like Wharfage for each country.
                @endif
            </p>
            <a href="{{ route('admin.country-levies.create', ['country_id' => $countryId]) }}" class="btn btn-primary btn-lg">
                <i class="fas fa-plus me-2"></i>Add First Levy
            </a>
        </div>
    </div>
    @endif

    {{-- Quick Setup Tip --}}
    <div class="card mt-4">
        <div class="card-header">
            <strong><i class="fas fa-lightbulb me-2"></i>Common Levy Types</strong>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <h6>Wharfage (WHA)</h6>
                    <p class="text-muted small mb-0">Typically 2% of FOB value. Charged on all imports for port handling.</p>
                </div>
                <div class="col-md-4">
                    <h6>Environmental Levy (ENV)</h6>
                    <p class="text-muted small mb-0">Fixed amount per shipment or percentage for environmental programs.</p>
                </div>
                <div class="col-md-4">
                    <h6>Service Charge (SVC)</h6>
                    <p class="text-muted small mb-0">Processing fees, often based on CIF value or fixed per declaration.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
