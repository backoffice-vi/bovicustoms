@extends('layouts.app')

@section('title', 'Duty Policies (FOB/CIF) - Admin')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-balance-scale me-2"></i>Duty Policies (FOB / CIF)</h2>
            <p class="text-muted mb-0">Per-country, time-bounded basis for Customs Duty calculation. When no row is in effect, the system falls back to CIF.</p>
        </div>
        <a href="{{ route('admin.country-duty-policies.create', ['country_id' => $countryId]) }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add Policy
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

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
                    <a href="{{ route('admin.country-duty-policies.index') }}" class="btn btn-outline-secondary">Clear Filter</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if($policies->count() > 0)
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Country</th>
                            <th>Basis</th>
                            <th>Effective From</th>
                            <th>Effective Until</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th style="width: 110px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($policies as $policy)
                        @php
                            $today = now()->toDateString();
                            $inWindow = $policy->effective_from && $policy->effective_from->lte(now())
                                && (!$policy->effective_until || $policy->effective_until->gte(now()));
                        @endphp
                        <tr>
                            <td>{{ $policy->country?->name ?? '-' }}</td>
                            <td>
                                @if($policy->basis === 'fob')
                                    <span class="badge bg-warning text-dark">FOB</span>
                                @else
                                    <span class="badge bg-info text-dark">CIF</span>
                                @endif
                            </td>
                            <td>{{ $policy->effective_from?->format('Y-m-d') ?? '-' }}</td>
                            <td>{{ $policy->effective_until?->format('Y-m-d') ?? 'Open-ended' }}</td>
                            <td><small>{{ $policy->source ?? '-' }}</small></td>
                            <td>
                                @if($policy->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                                @if($policy->is_active && $inWindow)
                                    <span class="badge bg-primary">In Effect Now</span>
                                @elseif($policy->is_active && !$inWindow)
                                    <span class="badge bg-warning text-dark">Outside Window</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.country-duty-policies.edit', $policy) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.country-duty-policies.destroy', $policy) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete this policy? Active declarations will revert to the default (CIF) for this country.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @if($policy->notes)
                        <tr>
                            <td colspan="7" class="bg-light"><small class="text-muted"><i class="fas fa-sticky-note me-1"></i>{{ $policy->notes }}</small></td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($policies->hasPages())
    <div class="mt-4">
        {{ $policies->links() }}
    </div>
    @endif
    @else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-balance-scale fa-4x text-muted mb-3"></i>
            <h4>No Duty Policies Configured</h4>
            <p class="text-muted mb-4">
                Without a policy, Customs Duty is computed on CIF (the default).
                Add a policy to override that for a specific country and date window — for example,
                temporary FOB-basis legislation.
            </p>
            <a href="{{ route('admin.country-duty-policies.create', ['country_id' => $countryId]) }}" class="btn btn-primary btn-lg">
                <i class="fas fa-plus me-2"></i>Add First Policy
            </a>
        </div>
    </div>
    @endif

    <div class="card mt-4">
        <div class="card-header">
            <strong><i class="fas fa-info-circle me-2"></i>How Duty Policies Work</strong>
        </div>
        <div class="card-body small">
            <ul class="mb-0">
                <li><strong>Default (no row in effect):</strong> Customs Duty is computed on <strong>CIF</strong> value.</li>
                <li><strong>Active row in effect:</strong> the row's <code>basis</code> is used for that country and declaration date.</li>
                <li>The basis only applies to <strong>Customs Duty (CUD)</strong>. Wharfage and other levies use their own per-row basis on Country Levies.</li>
                <li>Original duty rates are never modified — clearing or expiring a row reverts behavior automatically.</li>
            </ul>
        </div>
    </div>
</div>
@endsection
