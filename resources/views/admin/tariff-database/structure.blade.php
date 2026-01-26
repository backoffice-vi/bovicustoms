@extends('layouts.app')

@section('title', 'Chapters & Sections - Tariff Database')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-sitemap me-2"></i>Tariff Structure
            </h1>
            <p class="text-muted mb-0">
                {{ number_format($stats['chapters']) }} chapters, {{ number_format($stats['sections']) }} sections
            </p>
        </div>
        <div>
            <a href="{{ route('admin.tariff-database.index', ['country_id' => $countryId]) }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Overview
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
            </form>
        </div>
    </div>

    <div class="row">
        <!-- Sections Column -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Sections ({{ $sections->count() }})</h5>
                </div>
                <div class="card-body p-0" style="max-height: 70vh; overflow-y: auto;">
                    @if($sections->isEmpty())
                        <div class="alert alert-info m-3">
                            No sections found.
                        </div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach($sections as $section)
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="badge bg-secondary me-2">Section {{ $section->section_number }}</span>
                                            <strong>{{ $section->title }}</strong>
                                        </div>
                                        @if($section->notes->count() > 0)
                                            <span class="badge bg-info">{{ $section->notes->count() }} notes</span>
                                        @endif
                                    </div>
                                    @if($section->description)
                                        <small class="text-muted d-block mt-1">{{ Str::limit($section->description, 100) }}</small>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Chapters Column -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-book me-2"></i>Chapters ({{ $chapters->count() }})</h5>
                    <div>
                        <input type="text" id="chapterSearch" class="form-control form-control-sm" placeholder="Search chapters..." style="width: 200px;">
                    </div>
                </div>
                <div class="card-body p-0" style="max-height: 70vh; overflow-y: auto;">
                    @if($chapters->isEmpty())
                        <div class="alert alert-info m-3">
                            No chapters found. Process a law document to populate chapter data.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="chaptersTable">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width: 80px;">Chapter</th>
                                        <th>Title</th>
                                        <th style="width: 100px;">Section</th>
                                        <th style="width: 80px;">Notes</th>
                                        <th style="width: 100px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($chapters as $chapter)
                                        <tr class="chapter-row" data-search="{{ strtolower($chapter->chapter_number . ' ' . $chapter->title) }}">
                                            <td>
                                                <span class="badge bg-primary fs-6">{{ $chapter->chapter_number }}</span>
                                            </td>
                                            <td>
                                                <strong>{{ $chapter->title }}</strong>
                                                @if($chapter->description)
                                                    <br><small class="text-muted">{{ Str::limit($chapter->description, 80) }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                @if($chapter->section)
                                                    <span class="badge bg-secondary">{{ $chapter->section->section_number }}</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($chapter->notes->count() > 0)
                                                    <span class="badge bg-info">{{ $chapter->notes->count() }}</span>
                                                @else
                                                    <span class="text-muted">0</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.tariff-database.codes', ['country_id' => $countryId, 'chapter' => $chapter->chapter_number]) }}" 
                                                   class="btn btn-sm btn-outline-primary" title="View codes in this chapter">
                                                    <i class="fas fa-barcode"></i>
                                                </a>
                                                <a href="{{ route('admin.tariff-database.notes', ['country_id' => $countryId, 'chapter' => $chapter->chapter_number]) }}" 
                                                   class="btn btn-sm btn-outline-info" title="View notes for this chapter">
                                                    <i class="fas fa-sticky-note"></i>
                                                </a>
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

    <!-- Chapter Notes Summary -->
    @if($chapters->isNotEmpty())
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Notes Distribution by Chapter</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($chapters->filter(fn($c) => $c->notes->count() > 0)->take(20) as $chapter)
                        <div class="col-md-3 col-sm-6 mb-2">
                            <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded">
                                <span>
                                    <span class="badge bg-primary">Ch. {{ $chapter->chapter_number }}</span>
                                    <small class="ms-1">{{ Str::limit($chapter->title, 20) }}</small>
                                </span>
                                <span class="badge bg-info">{{ $chapter->notes->count() }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($chapters->filter(fn($c) => $c->notes->count() > 0)->count() > 20)
                    <p class="text-muted text-center mt-2 mb-0">
                        Showing top 20 chapters with notes. 
                        <a href="{{ route('admin.tariff-database.notes', ['country_id' => $countryId]) }}">View all notes</a>
                    </p>
                @endif
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('chapterSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.chapter-row').forEach(function(row) {
                const searchText = row.getAttribute('data-search');
                row.style.display = searchText.includes(searchTerm) ? '' : 'none';
            });
        });
    }
});
</script>
@endpush

<style>
.table th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 10;
}
</style>
@endsection
