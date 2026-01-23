@extends('layouts.app')

@section('title', 'Exemption Details - Admin')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.exemptions.index') }}">Exemptions</a></li>
                <li class="breadcrumb-item active">{{ $exemption->name }}</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">{{ $exemption->name }}</h4>
                    @if($exemption->is_active)
                        <span class="badge bg-success">Active</span>
                    @else
                        <span class="badge bg-secondary">Inactive</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($exemption->description)
                        <p>{{ $exemption->description }}</p>
                    @endif

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <strong>Country:</strong><br>
                            {{ $exemption->country->flag_emoji }} {{ $exemption->country->name }}
                        </div>
                        <div class="col-md-6">
                            <strong>Legal Reference:</strong><br>
                            {{ $exemption->legal_reference ?? 'Not specified' }}
                        </div>
                    </div>

                    @if($exemption->applies_to_patterns)
                        <div class="mb-4">
                            <strong>Applies to Tariff Codes:</strong><br>
                            @foreach($exemption->applies_to_patterns as $pattern)
                                <code class="me-2">{{ $pattern }}</code>
                            @endforeach
                        </div>
                    @endif

                    @if($exemption->valid_from || $exemption->valid_until)
                        <div class="mb-4">
                            <strong>Validity Period:</strong><br>
                            @if($exemption->valid_from)
                                From {{ $exemption->valid_from->format('M j, Y') }}
                            @endif
                            @if($exemption->valid_until)
                                Until {{ $exemption->valid_until->format('M j, Y') }}
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            @if($exemption->conditions->count() > 0)
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Conditions ({{ $exemption->conditions->count() }})</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach($exemption->conditions as $condition)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="badge bg-{{ $condition->is_mandatory ? 'danger' : 'secondary' }} me-2">
                                            {{ $condition->is_mandatory ? 'Mandatory' : 'Optional' }}
                                        </span>
                                        <span class="badge bg-info">{{ $condition->type_name }}</span>
                                    </div>
                                </div>
                                <h6 class="mt-2 mb-1">{{ $condition->description }}</h6>
                                @if($condition->requirement_text)
                                    <p class="text-muted small mb-0">{{ $condition->requirement_text }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('admin.exemptions.edit', $exemption) }}" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-edit me-1"></i> Edit Exemption
                    </a>
                    <a href="{{ route('admin.exemptions.index') }}" class="btn btn-secondary w-100">
                        <i class="fas fa-arrow-left me-1"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
