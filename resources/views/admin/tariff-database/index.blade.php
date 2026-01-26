@extends('layouts.app')

@section('title', 'Tariff Database Overview')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-database me-2"></i>Tariff Database Overview
            </h1>
            <p class="text-muted mb-0">View all tariff data stored in the original database</p>
        </div>
        <div>
            <a href="{{ route('admin.countries.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Countries
            </a>
        </div>
    </div>

    <!-- Country Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Filter by Country</label>
                    <select name="country_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Countries</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" {{ $countryId == $country->id ? 'selected' : '' }}>
                                {{ $country->flag_emoji }} {{ $country->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <!-- Customs Codes -->
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 opacity-75">Customs Codes</h6>
                            <h2 class="mb-0">{{ number_format($stats['customs_codes']) }}</h2>
                        </div>
                        <i class="fas fa-barcode fa-2x opacity-50"></i>
                    </div>
                    <div class="mt-2">
                        <small class="opacity-75">
                            {{ number_format($stats['codes_with_duty']) }} with duty |
                            {{ number_format($stats['codes_duty_free']) }} duty-free
                        </small>
                    </div>
                    <a href="{{ route('admin.tariff-database.codes', ['country_id' => $countryId]) }}" class="stretched-link"></a>
                </div>
            </div>
        </div>

        <!-- Chapters -->
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 opacity-75">Chapters</h6>
                            <h2 class="mb-0">{{ number_format($stats['chapters']) }}</h2>
                        </div>
                        <i class="fas fa-book fa-2x opacity-50"></i>
                    </div>
                    <small class="opacity-75">{{ $stats['sections'] }} sections</small>
                    <a href="{{ route('admin.tariff-database.structure', ['country_id' => $countryId]) }}" class="stretched-link"></a>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 opacity-75">Notes</h6>
                            <h2 class="mb-0">{{ number_format($stats['chapter_notes'] + $stats['section_notes']) }}</h2>
                        </div>
                        <i class="fas fa-sticky-note fa-2x opacity-50"></i>
                    </div>
                    <small class="opacity-75">
                        {{ $stats['chapter_notes'] }} chapter | {{ $stats['section_notes'] }} section
                    </small>
                    <a href="{{ route('admin.tariff-database.notes', ['country_id' => $countryId]) }}" class="stretched-link"></a>
                </div>
            </div>
        </div>

        <!-- Exclusion Rules -->
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 opacity-75">Exclusion Rules</h6>
                            <h2 class="mb-0">{{ number_format($stats['exclusion_rules']) }}</h2>
                        </div>
                        <i class="fas fa-ban fa-2x opacity-50"></i>
                    </div>
                    <small class="opacity-75">Parsed from notes</small>
                    <a href="{{ route('admin.tariff-database.exclusions', ['country_id' => $countryId]) }}" class="stretched-link"></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle text-muted mb-1">Exemptions</h6>
                            <h3 class="mb-0">{{ number_format($stats['exemptions']) }}</h3>
                        </div>
                        <i class="fas fa-certificate fa-2x text-muted"></i>
                    </div>
                    <small class="text-muted">{{ $stats['exemption_conditions'] ?? 0 }} conditions</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle text-muted mb-1">Prohibited Goods</h6>
                            <h3 class="mb-0">{{ number_format($stats['prohibited']) }}</h3>
                        </div>
                        <i class="fas fa-times-circle fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle text-muted mb-1">Restricted Goods</h6>
                            <h3 class="mb-0">{{ number_format($stats['restricted']) }}</h3>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle text-muted mb-1">Additional Levies</h6>
                            <h3 class="mb-0">{{ number_format($stats['levies']) }}</h3>
                        </div>
                        <i class="fas fa-coins fa-2x text-muted"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- More Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card h-100 border-secondary">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle text-muted mb-1">Warehousing Restrictions</h6>
                            <h3 class="mb-0">{{ number_format($stats['warehousing_restrictions'] ?? 0) }}</h3>
                        </div>
                        <i class="fas fa-warehouse fa-2x text-secondary"></i>
                    </div>
                    <small class="text-muted">Schedule 1 items</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Navigation -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-compass me-2"></i>Quick Navigation</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.tariff-database.codes', ['country_id' => $countryId]) }}" class="btn btn-outline-primary btn-lg w-100">
                                <i class="fas fa-barcode d-block mb-2" style="font-size: 2rem;"></i>
                                View All Codes
                                <small class="d-block text-muted">{{ number_format($stats['customs_codes']) }} codes</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.tariff-database.structure', ['country_id' => $countryId]) }}" class="btn btn-outline-success btn-lg w-100">
                                <i class="fas fa-sitemap d-block mb-2" style="font-size: 2rem;"></i>
                                Chapters & Sections
                                <small class="d-block text-muted">{{ $stats['chapters'] }} chapters</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.tariff-database.notes', ['country_id' => $countryId]) }}" class="btn btn-outline-info btn-lg w-100">
                                <i class="fas fa-sticky-note d-block mb-2" style="font-size: 2rem;"></i>
                                All Notes
                                <small class="d-block text-muted">{{ $stats['chapter_notes'] + $stats['section_notes'] }} notes</small>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="{{ route('admin.tariff-database.exclusions', ['country_id' => $countryId]) }}" class="btn btn-outline-warning btn-lg w-100">
                                <i class="fas fa-ban d-block mb-2" style="font-size: 2rem;"></i>
                                Exclusion Rules
                                <small class="d-block text-muted">{{ $stats['exclusion_rules'] }} rules</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Codes Preview -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Customs Codes</h5>
            <a href="{{ route('admin.tariff-database.codes', ['country_id' => $countryId]) }}" class="btn btn-sm btn-outline-primary">
                View All <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="card-body p-0">
            @if($recentCodes->isEmpty())
                <div class="alert alert-info m-3">
                    <i class="fas fa-info-circle me-2"></i>
                    No customs codes found in the database. 
                    <a href="{{ route('admin.law-documents.index') }}">Process a law document</a> to populate the database.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Description</th>
                                <th class="text-center">Duty Rate</th>
                                <th>Chapter</th>
                                <th>Country</th>
                                <th>Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentCodes as $code)
                                <tr>
                                    <td>
                                        <code class="text-primary fw-bold">{{ $code->code }}</code>
                                    </td>
                                    <td>{{ Str::limit($code->description, 60) }}</td>
                                    <td class="text-center">
                                        @if($code->duty_rate !== null)
                                            <span class="badge {{ $code->duty_rate > 0 ? 'bg-warning text-dark' : 'bg-success' }}">
                                                {{ number_format($code->duty_rate, 1) }}%
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($code->tariffChapter)
                                            <span class="badge bg-primary">Ch. {{ $code->tariffChapter->chapter_number }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($code->country)
                                            {{ $code->country->flag_emoji ?? '' }} {{ $code->country->code }}
                                        @endif
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $code->created_at->diffForHumans() }}</small>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Data Status Alert -->
    @if($stats['customs_codes'] == 0)
        <div class="alert alert-warning mt-4">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>No Tariff Data in Database</h5>
            <p class="mb-0">
                The original database has no customs codes. If you've sent data directly to Qdrant, it won't appear here.
                To populate the database, you need to process a law document through the 
                <a href="{{ route('admin.law-documents.index') }}">Law Documents</a> section.
            </p>
        </div>
    @endif
</div>
@endsection
