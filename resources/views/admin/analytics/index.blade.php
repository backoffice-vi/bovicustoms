@extends('layouts.app')

@section('title', 'Site Analytics - Admin')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-chart-line me-2"></i>Site Analytics
                    </h1>
                    <p class="text-muted mb-0">Track visitor activity and site performance</p>
                </div>
                <div class="d-flex gap-2">
                    <form method="GET" class="d-flex gap-2">
                        <select name="period" class="form-select" onchange="this.form.submit()">
                            <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Today</option>
                            <option value="yesterday" {{ $period === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                            <option value="7days" {{ $period === '7days' ? 'selected' : '' }}>Last 7 Days</option>
                            <option value="30days" {{ $period === '30days' ? 'selected' : '' }}>Last 30 Days</option>
                            <option value="90days" {{ $period === '90days' ? 'selected' : '' }}>Last 90 Days</option>
                            <option value="year" {{ $period === 'year' ? 'selected' : '' }}>Last Year</option>
                        </select>
                    </form>
                    <a href="{{ route('admin.analytics.export', ['period' => $period]) }}" class="btn btn-outline-primary">
                        <i class="fas fa-download me-1"></i>Export CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Overview Stats --}}
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-white-50 mb-1">Page Views</h6>
                            <h2 class="mb-0">{{ number_format($stats['total_page_views']) }}</h2>
                        </div>
                        <i class="fas fa-eye fa-2x text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-white-50 mb-1">Unique Visitors</h6>
                            <h2 class="mb-0">{{ number_format($stats['unique_visitors']) }}</h2>
                        </div>
                        <i class="fas fa-users fa-2x text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-white-50 mb-1">New Visitors</h6>
                            <h2 class="mb-0">{{ number_format($stats['new_visitors']) }}</h2>
                        </div>
                        <i class="fas fa-user-plus fa-2x text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-dark-50 mb-1">Returning</h6>
                            <h2 class="mb-0">{{ number_format($stats['returning_visitors']) }}</h2>
                        </div>
                        <i class="fas fa-redo fa-2x text-dark-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-secondary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-white-50 mb-1">Sessions</h6>
                            <h2 class="mb-0">{{ number_format($stats['unique_sessions']) }}</h2>
                        </div>
                        <i class="fas fa-clock fa-2x text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-dark text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="text-white-50 mb-1">Avg Response</h6>
                            <h2 class="mb-0">{{ $stats['avg_response_time'] }}ms</h2>
                        </div>
                        <i class="fas fa-tachometer-alt fa-2x text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        {{-- Page Views Chart --}}
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-area me-2"></i>Traffic Over Time</h5>
                </div>
                <div class="card-body">
                    <canvas id="trafficChart" height="100"></canvas>
                </div>
            </div>
        </div>

        {{-- Hourly Traffic (Today) --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Today's Hourly Traffic</h5>
                </div>
                <div class="card-body">
                    <canvas id="hourlyChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        {{-- Traffic by Country --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Traffic by Country</h5>
                </div>
                <div class="card-body p-0">
                    @if($trafficByCountry->isEmpty())
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-globe fa-3x mb-3"></i>
                            <p>No location data available yet</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Country</th>
                                        <th class="text-end">Visits</th>
                                        <th class="text-end">Visitors</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($trafficByCountry as $country)
                                    <tr>
                                        <td>
                                            @if($country->country_code)
                                                <span class="fi fi-{{ strtolower($country->country_code) }} me-2"></span>
                                            @endif
                                            {{ $country->country ?? 'Unknown' }}
                                        </td>
                                        <td class="text-end">{{ number_format($country->visits) }}</td>
                                        <td class="text-end">{{ number_format($country->visitors) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Device & Browser Stats --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-mobile-alt me-2"></i>Devices & Browsers</h5>
                </div>
                <div class="card-body">
                    <h6 class="text-muted mb-3">Device Type</h6>
                    @foreach($trafficByDevice as $device)
                        @php
                            $percentage = $stats['total_page_views'] > 0 
                                ? round(($device->visits / $stats['total_page_views']) * 100) 
                                : 0;
                            $icon = match($device->device_type) {
                                'desktop' => 'fa-desktop',
                                'mobile' => 'fa-mobile-alt',
                                'tablet' => 'fa-tablet-alt',
                                default => 'fa-question'
                            };
                        @endphp
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>
                                <i class="fas {{ $icon }} me-2 text-muted"></i>
                                {{ ucfirst($device->device_type) }}
                            </span>
                            <span class="badge bg-primary">{{ $percentage }}%</span>
                        </div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar" style="width: {{ $percentage }}%"></div>
                        </div>
                    @endforeach

                    <hr>
                    <h6 class="text-muted mb-3">Top Browsers</h6>
                    @foreach($trafficByBrowser as $browser)
                        @php
                            $percentage = $stats['total_page_views'] > 0 
                                ? round(($browser->visits / $stats['total_page_views']) * 100) 
                                : 0;
                        @endphp
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>{{ $browser->browser }}</span>
                            <span class="text-muted">{{ number_format($browser->visits) }} ({{ $percentage }}%)</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Top Pages --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file me-2"></i>Top Pages</h5>
                </div>
                <div class="card-body p-0">
                    @if($topPages->isEmpty())
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-file fa-3x mb-3"></i>
                            <p>No page data available yet</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Page</th>
                                        <th class="text-end">Views</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topPages as $page)
                                    <tr>
                                        <td>
                                            <small class="text-muted d-block">{{ $page->route_name }}</small>
                                            <small class="text-truncate d-block" style="max-width: 200px;" title="{{ $page->url }}">
                                                {{ parse_url($page->url, PHP_URL_PATH) ?: '/' }}
                                            </small>
                                        </td>
                                        <td class="text-end">{{ number_format($page->views) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        {{-- Top Referrers --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-external-link-alt me-2"></i>Top Referrers</h5>
                </div>
                <div class="card-body p-0">
                    @if($topReferrers->isEmpty())
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-link fa-3x mb-3"></i>
                            <p>No referrer data available yet</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th class="text-end">Visits</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topReferrers as $referrer)
                                    <tr>
                                        <td>
                                            <small class="text-truncate d-block" style="max-width: 250px;" title="{{ $referrer->referrer }}">
                                                {{ $referrer->domain }}
                                            </small>
                                        </td>
                                        <td class="text-end">{{ number_format($referrer->visits) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Recent Visits (Live Feed) --}}
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-stream me-2"></i>Recent Visits</h5>
                    <span class="badge bg-success">
                        <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>Live
                    </span>
                </div>
                <div class="card-body p-0">
                    @if($recentVisits->isEmpty())
                        <div class="p-4 text-center text-muted">
                            <i class="fas fa-hourglass-half fa-3x mb-3"></i>
                            <p>No visits recorded yet</p>
                        </div>
                    @else
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover mb-0 table-sm">
                                <thead class="sticky-top bg-white">
                                    <tr>
                                        <th>Time</th>
                                        <th>Page</th>
                                        <th>Location</th>
                                        <th>Device</th>
                                        <th>User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentVisits as $visit)
                                    <tr>
                                        <td>
                                            <small class="text-muted">{{ $visit->created_at->diffForHumans() }}</small>
                                        </td>
                                        <td>
                                            <small class="text-truncate d-block" style="max-width: 200px;" title="{{ $visit->url }}">
                                                {{ parse_url($visit->url, PHP_URL_PATH) ?: '/' }}
                                            </small>
                                        </td>
                                        <td>
                                            <small>
                                                @if($visit->city && $visit->country_code)
                                                    {{ $visit->city }}, {{ $visit->country_code }}
                                                @elseif($visit->country)
                                                    {{ $visit->country }}
                                                @else
                                                    <span class="text-muted">Unknown</span>
                                                @endif
                                            </small>
                                        </td>
                                        <td>
                                            @php
                                                $icon = match($visit->device_type) {
                                                    'desktop' => 'fa-desktop',
                                                    'mobile' => 'fa-mobile-alt',
                                                    'tablet' => 'fa-tablet-alt',
                                                    default => 'fa-question'
                                                };
                                            @endphp
                                            <i class="fas {{ $icon }} text-muted" title="{{ ucfirst($visit->device_type) }} - {{ $visit->browser }}"></i>
                                        </td>
                                        <td>
                                            @if($visit->user)
                                                <span class="badge bg-primary">{{ $visit->user->name }}</span>
                                            @else
                                                <span class="text-muted">Guest</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Bot Traffic Info --}}
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info mb-0">
                <i class="fas fa-robot me-2"></i>
                <strong>Bot Traffic:</strong> {{ number_format($stats['bot_visits']) }} bot visits detected and excluded from the above statistics.
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">
<style>
    .card {
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
    }
    .card-header {
        background: transparent;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .table th {
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #6c757d;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Traffic Over Time Chart
    const trafficCtx = document.getElementById('trafficChart').getContext('2d');
    const trafficData = @json($pageViewsByDay);
    
    new Chart(trafficCtx, {
        type: 'line',
        data: {
            labels: trafficData.map(d => d.date),
            datasets: [{
                label: 'Page Views',
                data: trafficData.map(d => d.views),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                fill: true,
                tension: 0.3
            }, {
                label: 'Unique Visitors',
                data: trafficData.map(d => d.visitors),
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Hourly Traffic Chart
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
    const hourlyData = @json($hourlyData);
    
    new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: Array.from({length: 24}, (_, i) => i + ':00'),
            datasets: [{
                label: 'Visits',
                data: Object.values(hourlyData),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgb(54, 162, 235)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                },
                x: {
                    ticks: {
                        maxRotation: 0,
                        callback: function(val, index) {
                            return index % 4 === 0 ? this.getLabelForValue(val) : '';
                        }
                    }
                }
            }
        }
    });
});
</script>
@endpush
