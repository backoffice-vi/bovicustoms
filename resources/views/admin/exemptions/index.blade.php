@extends('layouts.app')

@section('title', 'Exemptions - Admin')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-certificate me-2"></i>Exemption Categories
        </h1>
        <a href="{{ route('admin.exemptions.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Add Exemption
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Country</label>
                    <select name="country_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Countries</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" {{ $countryId == $country->id ? 'selected' : '' }}>
                                {{ $country->flag_emoji }} {{ $country->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Exemptions List -->
    <div class="row">
        @forelse($exemptions as $exemption)
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ $exemption->name }}</h5>
                        @if($exemption->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($exemption->description)
                            <p class="text-muted">{{ Str::limit($exemption->description, 150) }}</p>
                        @endif
                        
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="fas fa-flag me-1"></i> {{ $exemption->country->name }}
                            </small>
                            @if($exemption->legal_reference)
                                <small class="text-muted ms-3">
                                    <i class="fas fa-gavel me-1"></i> {{ $exemption->legal_reference }}
                                </small>
                            @endif
                        </div>

                        @if($exemption->applies_to_patterns)
                            <div class="mb-3">
                                <strong class="small">Applies to:</strong>
                                @foreach($exemption->applies_to_patterns as $pattern)
                                    <code class="me-1">{{ $pattern }}</code>
                                @endforeach
                            </div>
                        @endif

                        @if($exemption->conditions_count > 0)
                            <div class="mb-3">
                                <span class="badge bg-info">
                                    {{ $exemption->conditions_count }} condition(s)
                                </span>
                            </div>
                        @endif

                        @if($exemption->valid_from || $exemption->valid_until)
                            <div class="small text-muted">
                                @if($exemption->valid_from)
                                    From: {{ $exemption->valid_from->format('M j, Y') }}
                                @endif
                                @if($exemption->valid_until)
                                    Until: {{ $exemption->valid_until->format('M j, Y') }}
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('admin.exemptions.show', $exemption) }}" class="btn btn-outline-secondary">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="{{ route('admin.exemptions.edit', $exemption) }}" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('admin.exemptions.destroy', $exemption) }}" class="d-inline" onsubmit="return confirm('Delete this exemption?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No exemption categories found</p>
                        <a href="{{ route('admin.exemptions.create') }}" class="btn btn-primary">Add First Exemption</a>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    {{ $exemptions->withQueryString()->links() }}
</div>
@endsection
