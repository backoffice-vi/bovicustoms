@extends('layouts.app')

@section('title', 'Classification Logs - Admin')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-search me-2"></i>Public Classification Logs
        </h1>
        <a href="{{ route('admin.classification-logs.export', request()->all()) }}" class="btn btn-success">
            <i class="fas fa-download me-1"></i> Export CSV
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Searches</h6>
                            <h2 class="mb-0">{{ number_format($stats['total']) }}</h2>
                        </div>
                        <i class="fas fa-search fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Today</h6>
                            <h2 class="mb-0">{{ number_format($stats['today']) }}</h2>
                        </div>
                        <i class="fas fa-calendar-day fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">This Week</h6>
                            <h2 class="mb-0">{{ number_format($stats['this_week']) }}</h2>
                        </div>
                        <i class="fas fa-calendar-week fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Success Rate</h6>
                            <h2 class="mb-0">{{ $stats['success_rate'] }}%</h2>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Popular Searches -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-fire me-2 text-danger"></i>Popular Search Terms</h5>
                </div>
                <div class="card-body">
                    @if($popularSearches->count() > 0)
                        <ul class="list-group list-group-flush">
                            @foreach($popularSearches as $search)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>{{ Str::limit($search->search_term, 40) }}</span>
                                    <span class="badge bg-primary">{{ $search->count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">No searches yet.</p>
                    @endif
                </div>
            </div>
        </div>
        
        <!-- Popular HS Codes -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-barcode me-2 text-success"></i>Top HS Codes Returned</h5>
                </div>
                <div class="card-body">
                    @if($popularCodes->count() > 0)
                        <ul class="list-group list-group-flush">
                            @foreach($popularCodes as $code)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>
                                        <strong>{{ $code->result_code }}</strong>
                                        <small class="text-muted d-block">{{ Str::limit($code->result_description, 30) }}</small>
                                    </span>
                                    <span class="badge bg-success">{{ $code->count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">No results yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.classification-logs.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search term or HS code..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="success" {{ request('status') == 'success' ? 'selected' : '' }}>Successful</option>
                        <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-secondary me-2">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                    <a href="{{ route('admin.classification-logs.index') }}" class="btn btn-outline-secondary">
                        Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Search Term</th>
                        <th>Result Code</th>
                        <th>Duty Rate</th>
                        <th>Confidence</th>
                        <th>Status</th>
                        <th style="width: 150px">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>
                                <strong>{{ Str::limit($log->search_term, 50) }}</strong>
                            </td>
                            <td>
                                @if($log->result_code)
                                    <code>{{ $log->result_code }}</code>
                                    <small class="text-muted d-block">{{ Str::limit($log->result_description, 30) }}</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($log->duty_rate !== null)
                                    <span class="badge bg-info">{{ $log->duty_rate }}%</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($log->confidence !== null)
                                    <span class="badge {{ $log->confidence >= 80 ? 'bg-success' : ($log->confidence >= 50 ? 'bg-warning' : 'bg-danger') }}">
                                        {{ $log->confidence }}%
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($log->success)
                                    <span class="badge bg-success">Success</span>
                                @else
                                    <span class="badge bg-danger" title="{{ $log->error_message }}">Failed</span>
                                @endif
                            </td>
                            <td>
                                <small class="text-muted">
                                    {{ $log->created_at->format('M d, Y') }}<br>
                                    {{ $log->created_at->format('h:i A') }}
                                </small>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-search fa-3x mb-3 d-block"></i>
                                <p class="mb-0">No classification logs yet.</p>
                                <p class="small">Logs will appear here when visitors use the public classifier.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="card-footer">
                {{ $logs->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
