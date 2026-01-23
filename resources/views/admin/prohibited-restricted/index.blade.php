@extends('layouts.app')

@section('title', 'Prohibited & Restricted Goods - Admin')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-exclamation-triangle me-2"></i>Prohibited & Restricted Goods
        </h1>
        <div class="btn-group">
            <a href="{{ route('admin.prohibited-restricted.create-prohibited') }}" class="btn btn-danger">
                <i class="fas fa-ban me-1"></i> Add Prohibited
            </a>
            <a href="{{ route('admin.prohibited-restricted.create-restricted') }}" class="btn btn-warning">
                <i class="fas fa-lock me-1"></i> Add Restricted
            </a>
        </div>
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
                <div class="col-md-4">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="all" {{ $type == 'all' ? 'selected' : '' }}>All</option>
                        <option value="prohibited" {{ $type == 'prohibited' ? 'selected' : '' }}>Prohibited Only</option>
                        <option value="restricted" {{ $type == 'restricted' ? 'selected' : '' }}>Restricted Only</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Prohibited Goods -->
        @if($type == 'all' || $type == 'prohibited')
            <div class="{{ $type == 'all' ? 'col-md-6' : 'col-12' }}">
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-ban me-2"></i>Prohibited Goods</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse($prohibited as $item)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ $item->name }}</h6>
                                        @if($item->description)
                                            <p class="text-muted small mb-1">{{ Str::limit($item->description, 100) }}</p>
                                        @endif
                                        @if($item->legal_reference)
                                            <small class="text-muted"><i class="fas fa-gavel me-1"></i>{{ $item->legal_reference }}</small>
                                        @endif
                                        @if($item->detection_keywords)
                                            <div class="mt-1">
                                                @foreach($item->detection_keywords as $kw)
                                                    <span class="badge bg-secondary me-1">{{ $kw }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('admin.prohibited-restricted.destroy-prohibited', $item) }}" onsubmit="return confirm('Delete this item?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="list-group-item text-center text-muted py-4">
                                No prohibited goods found
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        <!-- Restricted Goods -->
        @if($type == 'all' || $type == 'restricted')
            <div class="{{ $type == 'all' ? 'col-md-6' : 'col-12' }}">
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Restricted Goods</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse($restricted as $item)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">{{ $item->name }}</h6>
                                        @if($item->description)
                                            <p class="text-muted small mb-1">{{ Str::limit($item->description, 100) }}</p>
                                        @endif
                                        <div class="small">
                                            @if($item->restriction_type)
                                                <span class="badge bg-info me-1">{{ $item->restriction_type_name }}</span>
                                            @endif
                                            @if($item->permit_authority)
                                                <span class="text-muted"><i class="fas fa-user-shield me-1"></i>{{ $item->permit_authority }}</span>
                                            @endif
                                        </div>
                                        @if($item->detection_keywords)
                                            <div class="mt-1">
                                                @foreach($item->detection_keywords as $kw)
                                                    <span class="badge bg-secondary me-1">{{ $kw }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('admin.prohibited-restricted.destroy-restricted', $item) }}" onsubmit="return confirm('Delete this item?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="list-group-item text-center text-muted py-4">
                                No restricted goods found
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
