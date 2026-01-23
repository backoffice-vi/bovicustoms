@extends('layouts.app')

@section('title', 'Countries - Admin')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-globe me-2"></i>Countries
        </h1>
        <a href="{{ route('admin.countries.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Add Country
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

    <!-- Countries Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px">Flag</th>
                        <th style="width: 100px">Code</th>
                        <th>Name</th>
                        <th style="width: 120px">Currency</th>
                        <th style="width: 120px">Insurance</th>
                        <th style="width: 100px">Status</th>
                        <th style="width: 150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($countries as $country)
                        <tr>
                            <td class="text-center">
                                @if($country->flag_emoji)
                                    <span style="font-size: 1.5rem;">{{ $country->flag_emoji }}</span>
                                @else
                                    <i class="fas fa-flag text-muted"></i>
                                @endif
                            </td>
                            <td>
                                <strong class="font-monospace">{{ $country->code }}</strong>
                            </td>
                            <td>
                                {{ $country->name }}
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $country->currency_code }}</span>
                            </td>
                            <td>
                                @if($country->default_insurance_percentage)
                                    <span class="badge bg-info">{{ number_format($country->default_insurance_percentage, 2) }}%</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($country->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.countries.show', $country) }}" class="btn btn-outline-info" title="View Tariff Data">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.countries.edit', $country) }}" class="btn btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.countries.destroy', $country) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this country? This may affect customs codes and other data.');">
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
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fas fa-globe fa-3x mb-3 d-block"></i>
                                <p class="mb-2">No countries found.</p>
                                <a href="{{ route('admin.countries.create') }}" class="btn btn-primary btn-sm mt-2">
                                    <i class="fas fa-plus me-1"></i> Add First Country
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($countries->hasPages())
            <div class="card-footer">
                {{ $countries->links() }}
            </div>
        @endif
    </div>

    <!-- Info Panel -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>About Countries</h5>
                    <p class="card-text mb-0">
                        Countries are used to configure customs operations. Each country can have its own customs codes, duty rates, and declaration forms.
                        Inactive countries will not be available for selection in other parts of the system.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
