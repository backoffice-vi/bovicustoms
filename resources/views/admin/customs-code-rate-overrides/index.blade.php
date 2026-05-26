@extends('layouts.app')

@section('title', 'Customs Code Rate Overrides - Admin')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-percent me-2"></i>Customs Code Rate Overrides</h2>
            <p class="text-muted mb-0">Time-bounded duty-rate overrides for individual codes ("basket of goods"). Original rates on Customs Codes are not modified.</p>
        </div>
        <div class="btn-group">
            <a href="{{ route('admin.customs-code-rate-overrides.bulk', ['country_id' => $countryId]) }}" class="btn btn-outline-primary">
                <i class="fas fa-layer-group me-1"></i>Bulk Add
            </a>
            <a href="{{ route('admin.customs-code-rate-overrides.create', ['country_id' => $countryId]) }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Add Single
            </a>
        </div>
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
                <div class="col-md-5">
                    <label class="form-label">Search by Code or Description</label>
                    <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="e.g. 1006 or rice">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">Apply</button>
                    @if($countryId || $search)
                    <a href="{{ route('admin.customs-code-rate-overrides.index') }}" class="btn btn-outline-secondary">Clear</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if($overrides->count() > 0)
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Country</th>
                            <th>HS Code</th>
                            <th>Description</th>
                            <th class="text-end">Base Rate</th>
                            <th class="text-end">Override Rate</th>
                            <th>Effective</th>
                            <th>Status</th>
                            <th style="width: 110px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($overrides as $override)
                        @php
                            $inWindow = $override->effective_from && $override->effective_from->lte(now())
                                && (!$override->effective_until || $override->effective_until->gte(now()));
                            $baseRate = $override->customsCode?->duty_rate;
                        @endphp
                        <tr>
                            <td>{{ $override->country?->name ?? '-' }}</td>
                            <td><code>{{ $override->customsCode?->code ?? '-' }}</code></td>
                            <td><small>{{ \Illuminate\Support\Str::limit($override->customsCode?->description ?? '', 80) }}</small></td>
                            <td class="text-end">{{ $baseRate !== null ? number_format((float) $baseRate, 2) . '%' : '-' }}</td>
                            <td class="text-end">
                                <strong>{{ number_format((float) $override->override_rate, 2) }}%</strong>
                                @if($baseRate !== null && (float) $override->override_rate < (float) $baseRate)
                                    <i class="fas fa-arrow-down text-success ms-1" title="Lower than base"></i>
                                @elseif($baseRate !== null && (float) $override->override_rate > (float) $baseRate)
                                    <i class="fas fa-arrow-up text-danger ms-1" title="Higher than base"></i>
                                @endif
                            </td>
                            <td>
                                <small>
                                    {{ $override->effective_from?->format('Y-m-d') ?? '-' }}
                                    →
                                    {{ $override->effective_until?->format('Y-m-d') ?? 'Open' }}
                                </small>
                            </td>
                            <td>
                                @if($override->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                                @if($override->is_active && $inWindow)
                                    <span class="badge bg-primary">In Effect Now</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.customs-code-rate-overrides.edit', $override) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('admin.customs-code-rate-overrides.destroy', $override) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete this override?')">
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

    @if($overrides->hasPages())
    <div class="mt-4">
        {{ $overrides->links() }}
    </div>
    @endif
    @else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-percent fa-4x text-muted mb-3"></i>
            <h4>No Rate Overrides Configured</h4>
            <p class="text-muted mb-4">
                Add overrides when a government temporarily changes the duty rate on a specific code or basket of goods.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="{{ route('admin.customs-code-rate-overrides.bulk', ['country_id' => $countryId]) }}" class="btn btn-outline-primary btn-lg">
                    <i class="fas fa-layer-group me-1"></i>Bulk Add
                </a>
                <a href="{{ route('admin.customs-code-rate-overrides.create', ['country_id' => $countryId]) }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus me-1"></i>Add Single
                </a>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
