@extends('layouts.app')

@section('title', 'Customs Codes - Admin')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-barcode me-2"></i>Customs Codes
        </h1>
        <a href="{{ route('admin.customs-codes.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Add Code
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
            <form method="GET" action="{{ route('admin.customs-codes.index') }}" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Country</label>
                    <select name="country_id" class="form-select">
                        <option value="">All Countries</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" {{ request('country_id') == $country->id ? 'selected' : '' }}>
                                {{ $country->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by code or description..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-secondary me-2">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                    <a href="{{ route('admin.customs-codes.index') }}" class="btn btn-outline-secondary">
                        Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Codes Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 150px">Code</th>
                        <th>Description</th>
                        <th style="width: 150px">Country</th>
                        <th style="width: 100px">Duty Rate</th>
                        <th style="width: 100px">HS Version</th>
                        <th style="width: 150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($codes as $code)
                        <tr>
                            <td>
                                <strong class="font-monospace">{{ $code->code }}</strong>
                            </td>
                            <td>
                                {{ Str::limit($code->description, 80) }}
                            </td>
                            <td>
                                @if($code->country)
                                    <i class="fas fa-flag me-1"></i>{{ $code->country->name }}
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-primary">{{ number_format($code->duty_rate, 2) }}%</span>
                            </td>
                            <td>
                                <span class="text-muted">{{ $code->hs_code_version ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.customs-codes.edit', $code) }}" class="btn btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.customs-codes.destroy', $code) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this customs code?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-barcode fa-3x mb-3 d-block"></i>
                                <p class="mb-2">No customs codes found.</p>
                                @if(request()->has('search') || request()->has('country_id'))
                                    <p class="mb-2">Try adjusting your filters.</p>
                                    <a href="{{ route('admin.customs-codes.index') }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                                @else
                                    <a href="{{ route('admin.customs-codes.create') }}" class="btn btn-primary btn-sm mt-2">
                                        <i class="fas fa-plus me-1"></i> Add First Code
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($codes->hasPages())
            <div class="card-footer">
                {{ $codes->links() }}
            </div>
        @endif
    </div>

    <!-- Info Panel -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>About Customs Codes</h5>
                    <p class="card-text mb-0">
                        Customs codes (HS codes) are used to classify goods for customs purposes. Each code has an associated duty rate.
                        The system uses these codes to automatically calculate duties and generate declaration forms.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
