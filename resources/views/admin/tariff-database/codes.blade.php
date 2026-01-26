@extends('layouts.app')

@section('title', 'Customs Codes - Tariff Database')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-barcode me-2"></i>Customs Codes
            </h1>
            <p class="text-muted mb-0">
                {{ number_format($stats['customs_codes']) }} codes in database
                @if($countryId)
                    (filtered)
                @endif
            </p>
        </div>
        <div>
            <a href="{{ route('admin.tariff-database.index', ['country_id' => $countryId]) }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Overview
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Country</label>
                    <select name="country_id" class="form-select">
                        <option value="">All Countries</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" {{ $countryId == $country->id ? 'selected' : '' }}>
                                {{ $country->flag_emoji }} {{ $country->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Chapter</label>
                    <select name="chapter" class="form-select">
                        <option value="">All Chapters</option>
                        @foreach($chapters as $ch)
                            <option value="{{ $ch->chapter_number }}" {{ $chapter == $ch->chapter_number ? 'selected' : '' }}>
                                Ch. {{ $ch->chapter_number }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Code or description..." value="{{ $search }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                    <a href="{{ route('admin.tariff-database.codes') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats Summary -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between">
                        <span>Total Codes:</span>
                        <strong>{{ number_format($stats['customs_codes']) }}</strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between">
                        <span>With Duty (>0%):</span>
                        <strong class="text-warning">{{ number_format($stats['codes_with_duty']) }}</strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between">
                        <span>Duty Free (0%):</span>
                        <strong class="text-success">{{ number_format($stats['codes_duty_free']) }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Codes Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                Showing {{ $codes->firstItem() ?? 0 }} - {{ $codes->lastItem() ?? 0 }} of {{ number_format($codes->total()) }} codes
            </span>
            <div>
                <small class="text-muted me-2">Per page:</small>
                <select onchange="window.location.href=this.value" class="form-select form-select-sm d-inline-block" style="width: auto;">
                    @foreach([50, 100, 250, 500] as $pp)
                        <option value="{{ request()->fullUrlWithQuery(['per_page' => $pp]) }}" {{ request('per_page', 100) == $pp ? 'selected' : '' }}>
                            {{ $pp }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            @if($codes->isEmpty())
                <div class="alert alert-info m-3">
                    <i class="fas fa-info-circle me-2"></i>
                    No customs codes found matching your criteria.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0" id="codesTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 120px;">Code</th>
                                <th>Description</th>
                                <th class="text-center" style="width: 100px;">Duty Rate</th>
                                <th class="text-center" style="width: 100px;">Special Rate</th>
                                <th style="width: 80px;">Unit</th>
                                <th style="width: 100px;">Chapter</th>
                                <th style="width: 80px;">Country</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($codes as $code)
                                <tr>
                                    <td>
                                        <code class="text-primary fw-bold fs-6">{{ $code->code }}</code>
                                        @if($code->code_level)
                                            <br><small class="text-muted">{{ ucfirst($code->code_level) }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span title="{{ $code->description }}">{{ $code->description }}</span>
                                        @if($code->notes)
                                            <br><small class="text-info"><i class="fas fa-sticky-note me-1"></i>{{ Str::limit($code->notes, 50) }}</small>
                                        @endif
                                        @if($code->inclusion_hints)
                                            <br><small class="text-success"><i class="fas fa-plus-circle me-1"></i>{{ Str::limit($code->inclusion_hints, 50) }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($code->duty_type === 'specific')
                                            <span class="badge bg-info text-dark fs-6" title="Specific duty">
                                                ${{ number_format($code->specific_duty_amount, 2) }}
                                            </span>
                                            <br><small class="text-muted">{{ $code->specific_duty_unit }}</small>
                                        @elseif($code->duty_type === 'compound')
                                            <span class="badge bg-warning text-dark fs-6">
                                                {{ number_format($code->duty_rate, 1) }}%
                                            </span>
                                            <br><small class="text-info">+ ${{ number_format($code->specific_duty_amount, 2) }} {{ $code->specific_duty_unit }}</small>
                                        @elseif($code->duty_rate !== null)
                                            <span class="badge {{ $code->duty_rate > 0 ? 'bg-warning text-dark' : 'bg-success' }} fs-6">
                                                {{ $code->duty_rate > 0 ? number_format($code->duty_rate, 1) . '%' : 'Free' }}
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">N/A</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($code->special_rate !== null)
                                            <span class="badge bg-info">{{ number_format($code->special_rate, 1) }}%</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small>{{ $code->unit_of_measurement ?? '-' }}</small>
                                        @if($code->unit_secondary)
                                            <br><small class="text-muted">& {{ $code->unit_secondary }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($code->tariffChapter)
                                            <span class="badge bg-primary">Ch. {{ $code->tariffChapter->chapter_number }}</span>
                                            <br><small class="text-muted" title="{{ $code->tariffChapter->title }}">{{ Str::limit($code->tariffChapter->title, 20) }}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($code->country)
                                            {{ $code->country->flag_emoji ?? '' }}
                                            <small>{{ $code->country->code }}</small>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if($codes->hasPages())
            <div class="card-footer">
                {{ $codes->links() }}
            </div>
        @endif
    </div>
</div>

<style>
.table th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 10;
}
#codesTable tbody tr:hover {
    background-color: #e8f4fc !important;
}
</style>
@endsection
