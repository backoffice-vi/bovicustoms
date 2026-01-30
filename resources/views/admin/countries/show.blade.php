@extends('layouts.app')

@section('title', $country->name . ' - Country Details')

@section('content')
<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <span style="font-size: 2.5rem;" class="me-3">{{ $country->flag_emoji }}</span>
            <div>
                <h1 class="h3 mb-0">{{ $country->name }}</h1>
                <small class="text-muted">
                <small class="text-muted">
                    <span class="font-monospace">{{ $country->code }}</span> · 
                    {{ $country->currency_code }}
                    @if($country->is_active)
                        <span class="badge bg-success ms-2">Active</span>
                    @else
                        <span class="badge bg-secondary ms-2">Inactive</span>
                    @endif
                </small>
            </div>
        </div>
        <div>
            <a href="{{ route('admin.countries.edit', $country) }}" class="btn btn-outline-primary me-2">
                <i class="fas fa-edit me-1"></i> Edit
            </a>
            <a href="{{ route('admin.countries.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 opacity-75">Tariff Chapters</h6>
                            <h2 class="mb-0">{{ $stats['chapters_count'] }}</h2>
                        </div>
                        <i class="fas fa-book fa-2x opacity-50"></i>
                    </div>
                    <small class="opacity-75">{{ $stats['chapter_notes_count'] }} notes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 opacity-75">Customs Codes</h6>
                            <h2 class="mb-0">{{ number_format($stats['codes_count']) }}</h2>
                        </div>
                        <i class="fas fa-barcode fa-2x opacity-50"></i>
                    </div>
                    <small class="opacity-75">
                        <a href="{{ route('admin.customs-codes.index', ['country_id' => $country->id]) }}" class="text-white">View All →</a>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 opacity-75">Exemptions</h6>
                            <h2 class="mb-0">{{ $stats['exemptions_count'] }}</h2>
                        </div>
                        <i class="fas fa-certificate fa-2x opacity-50"></i>
                    </div>
                    <small class="opacity-75">Duty-free categories</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 opacity-75">Restrictions</h6>
                            <h2 class="mb-0">{{ $stats['prohibited_count'] + $stats['restricted_count'] }}</h2>
                        </div>
                        <i class="fas fa-ban fa-2x opacity-50"></i>
                    </div>
                    <small>{{ $stats['prohibited_count'] }} prohibited, {{ $stats['restricted_count'] }} restricted</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card bg-secondary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 opacity-75">Reference Data</h6>
                            <h2 class="mb-0">{{ number_format($stats['reference_count']) }}</h2>
                        </div>
                        <i class="fas fa-database fa-2x opacity-50"></i>
                    </div>
                    <small class="opacity-75">Carriers, Ports, CPCs, etc.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="countryTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="chapters-tab" data-bs-toggle="tab" data-bs-target="#chapters" type="button" role="tab">
                <i class="fas fa-book me-1"></i> Chapters & Notes
                <span class="badge bg-primary ms-1">{{ $stats['chapters_count'] }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sections" type="button" role="tab">
                <i class="fas fa-layer-group me-1"></i> Sections
                <span class="badge bg-secondary ms-1">{{ $stats['sections_count'] }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="exemptions-tab" data-bs-toggle="tab" data-bs-target="#exemptions" type="button" role="tab">
                <i class="fas fa-certificate me-1"></i> Exemptions
                <span class="badge bg-info ms-1">{{ $stats['exemptions_count'] }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="restrictions-tab" data-bs-toggle="tab" data-bs-target="#restrictions" type="button" role="tab">
                <i class="fas fa-ban me-1"></i> Prohibited/Restricted
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="levies-tab" data-bs-toggle="tab" data-bs-target="#levies" type="button" role="tab">
                <i class="fas fa-coins me-1"></i> Additional Levies
                <span class="badge bg-warning text-dark ms-1">{{ $stats['levies_count'] }}</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="reference-tab" data-bs-toggle="tab" data-bs-target="#reference" type="button" role="tab">
                <i class="fas fa-database me-1"></i> Reference Data
                <span class="badge bg-secondary ms-1">{{ $stats['reference_count'] }}</span>
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="countryTabsContent">
        
        <!-- Chapters & Notes Tab -->
        <div class="tab-pane fade show active" id="chapters" role="tabpanel">
            @if($chapters->isEmpty())
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No tariff chapters found for this country. Process a law document to populate this data.
                </div>
            @else
                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" id="chapterSearch" class="form-control" placeholder="Search chapters by number or title...">
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-outline-secondary btn-sm" id="expandAllChapters">
                            <i class="fas fa-expand-alt me-1"></i> Expand All
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" id="collapseAllChapters">
                            <i class="fas fa-compress-alt me-1"></i> Collapse All
                        </button>
                    </div>
                </div>
                
                <div class="accordion" id="chaptersAccordion">
                    @foreach($chapters as $chapter)
                        <div class="accordion-item chapter-item" data-search="{{ strtolower($chapter->chapter_number . ' ' . $chapter->title) }}">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#chapter-{{ $chapter->id }}">
                                    <span class="badge bg-primary me-2">Ch. {{ $chapter->chapter_number }}</span>
                                    <strong>{{ $chapter->title }}</strong>
                                    @if($chapter->notes->count() > 0)
                                        <span class="badge bg-secondary ms-2">{{ $chapter->notes->count() }} notes</span>
                                    @endif
                                </button>
                            </h2>
                            <div id="chapter-{{ $chapter->id }}" class="accordion-collapse collapse" data-bs-parent="#chaptersAccordion">
                                <div class="accordion-body">
                                    @if($chapter->description)
                                        <p class="text-muted">{{ $chapter->description }}</p>
                                    @endif
                                    
                                    @if($chapter->section)
                                        <p class="small">
                                            <i class="fas fa-layer-group me-1"></i>
                                            Section {{ $chapter->section->section_number }}: {{ $chapter->section->title }}
                                        </p>
                                    @endif
                                    
                                    @if($chapter->notes->count() > 0)
                                        <h6 class="mt-3 mb-2"><i class="fas fa-sticky-note me-1"></i> Chapter Notes</h6>
                                        <div class="list-group list-group-flush">
                                            @foreach($chapter->notes as $note)
                                                <div class="list-group-item px-0">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="flex-grow-1">
                                                            @if($note->note_number)
                                                                <strong class="me-2">Note {{ $note->note_number }}:</strong>
                                                            @endif
                                                            <span class="badge 
                                                                @if($note->note_type === 'exclusion') bg-danger
                                                                @elseif($note->note_type === 'definition') bg-info
                                                                @elseif($note->note_type === 'subheading_note') bg-warning text-dark
                                                                @else bg-secondary
                                                                @endif me-2">
                                                                {{ ucfirst(str_replace('_', ' ', $note->note_type)) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="mt-2 note-text" style="white-space: pre-wrap;">{{ $note->note_text }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-muted small"><em>No notes for this chapter</em></p>
                                    @endif
                                    
                                    <div class="mt-3">
                                        <a href="{{ route('admin.customs-codes.index', ['country_id' => $country->id, 'search' => $chapter->chapter_number]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-barcode me-1"></i> View Codes in Chapter {{ $chapter->chapter_number }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Sections Tab -->
        <div class="tab-pane fade" id="sections" role="tabpanel">
            @if($sections->isEmpty())
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No tariff sections found for this country.
                </div>
            @else
                <div class="accordion" id="sectionsAccordion">
                    @foreach($sections as $section)
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#section-{{ $section->id }}">
                                    <span class="badge bg-secondary me-2">Section {{ $section->section_number }}</span>
                                    <strong>{{ $section->title }}</strong>
                                    @if($section->notes->count() > 0)
                                        <span class="badge bg-info ms-2">{{ $section->notes->count() }} notes</span>
                                    @endif
                                </button>
                            </h2>
                            <div id="section-{{ $section->id }}" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    @if($section->description)
                                        <p class="text-muted">{{ $section->description }}</p>
                                    @endif
                                    
                                    @if($section->notes->count() > 0)
                                        <h6 class="mt-3 mb-2"><i class="fas fa-sticky-note me-1"></i> Section Notes</h6>
                                        <div class="list-group list-group-flush">
                                            @foreach($section->notes as $note)
                                                <div class="list-group-item px-0">
                                                    <div class="d-flex align-items-start">
                                                        @if($note->note_number)
                                                            <strong class="me-2">Note {{ $note->note_number }}:</strong>
                                                        @endif
                                                        <span class="badge 
                                                            @if($note->note_type === 'exclusion') bg-danger
                                                            @elseif($note->note_type === 'definition') bg-info
                                                            @else bg-secondary
                                                            @endif me-2">
                                                            {{ ucfirst(str_replace('_', ' ', $note->note_type ?? 'general')) }}
                                                        </span>
                                                    </div>
                                                    <div class="mt-2" style="white-space: pre-wrap;">{{ $note->note_text }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-muted small"><em>No notes for this section</em></p>
                                    @endif
                                    
                                    <!-- List chapters in this section -->
                                    @php
                                        $sectionChapters = $chapters->where('tariff_section_id', $section->id);
                                    @endphp
                                    @if($sectionChapters->count() > 0)
                                        <h6 class="mt-3 mb-2"><i class="fas fa-book me-1"></i> Chapters in this Section</h6>
                                        <ul class="list-unstyled">
                                            @foreach($sectionChapters as $ch)
                                                <li class="mb-1">
                                                    <span class="badge bg-primary">Ch. {{ $ch->chapter_number }}</span>
                                                    {{ $ch->title }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Exemptions Tab -->
        <div class="tab-pane fade" id="exemptions" role="tabpanel">
            @if($exemptions->isEmpty())
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No exemption categories found for this country.
                </div>
            @else
                <div class="row">
                    @foreach($exemptions as $exemption)
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
                                        <p class="text-muted">{{ $exemption->description }}</p>
                                    @endif
                                    
                                    @if($exemption->legal_reference)
                                        <p class="small">
                                            <i class="fas fa-gavel me-1"></i>
                                            <strong>Legal Reference:</strong> {{ $exemption->legal_reference }}
                                        </p>
                                    @endif
                                    
                                    @if($exemption->applies_to_patterns)
                                        <div class="mb-3">
                                            <strong class="small">Applies to codes:</strong><br>
                                            @foreach($exemption->applies_to_patterns as $pattern)
                                                <code class="me-1">{{ $pattern }}</code>
                                            @endforeach
                                        </div>
                                    @endif
                                    
                                    @if($exemption->conditions->count() > 0)
                                        <h6 class="mt-3"><i class="fas fa-clipboard-check me-1"></i> Conditions</h6>
                                        <ul class="list-unstyled small">
                                            @foreach($exemption->conditions as $condition)
                                                <li class="mb-2">
                                                    <span class="badge bg-outline-secondary me-1">{{ ucfirst($condition->condition_type) }}</span>
                                                    {{ $condition->description }}
                                                    @if($condition->requirement_text)
                                                        <br><small class="text-muted">{{ $condition->requirement_text }}</small>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Restrictions Tab -->
        <div class="tab-pane fade" id="restrictions" role="tabpanel">
            <div class="row">
                <!-- Prohibited Goods -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="fas fa-times-circle me-2"></i> Prohibited Goods</h5>
                        </div>
                        <div class="card-body">
                            @if($prohibitedGoods->isEmpty())
                                <p class="text-muted">No prohibited goods defined for this country.</p>
                            @else
                                <div class="list-group list-group-flush">
                                    @foreach($prohibitedGoods as $good)
                                        <div class="list-group-item px-0">
                                            <strong>{{ $good->name }}</strong>
                                            @if($good->description)
                                                <p class="text-muted small mb-1">{{ $good->description }}</p>
                                            @endif
                                            @if($good->legal_reference)
                                                <small class="text-muted">
                                                    <i class="fas fa-gavel me-1"></i>{{ $good->legal_reference }}
                                                </small>
                                            @endif
                                            @if($good->detection_keywords)
                                                <div class="mt-1">
                                                    @foreach($good->detection_keywords as $keyword)
                                                        <span class="badge bg-light text-dark me-1">{{ $keyword }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- Restricted Goods -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i> Restricted Goods</h5>
                        </div>
                        <div class="card-body">
                            @if($restrictedGoods->isEmpty())
                                <p class="text-muted">No restricted goods defined for this country.</p>
                            @else
                                <div class="list-group list-group-flush">
                                    @foreach($restrictedGoods as $good)
                                        <div class="list-group-item px-0">
                                            <strong>{{ $good->name }}</strong>
                                            @if($good->restriction_type)
                                                <span class="badge bg-warning text-dark ms-2">{{ ucfirst($good->restriction_type) }}</span>
                                            @endif
                                            @if($good->description)
                                                <p class="text-muted small mb-1">{{ $good->description }}</p>
                                            @endif
                                            @if($good->permit_authority)
                                                <p class="small mb-1">
                                                    <i class="fas fa-building me-1"></i>
                                                    <strong>Authority:</strong> {{ $good->permit_authority }}
                                                </p>
                                            @endif
                                            @if($good->requirements)
                                                <p class="small mb-1">
                                                    <i class="fas fa-clipboard-list me-1"></i>
                                                    {{ $good->requirements }}
                                                </p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Levies Tab -->
        <div class="tab-pane fade" id="levies" role="tabpanel">
            @if($levies->isEmpty())
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No additional levies defined for this country.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Levy Name</th>
                                <th>Rate</th>
                                <th>Rate Type</th>
                                <th>Chapter</th>
                                <th>Legal Reference</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($levies as $levy)
                                <tr>
                                    <td><strong>{{ $levy->levy_name }}</strong></td>
                                    <td>
                                        @if($levy->rate_type === 'percentage')
                                            {{ number_format($levy->rate, 2) }}%
                                        @else
                                            ${{ number_format($levy->rate, 2) }}
                                            @if($levy->unit)
                                                / {{ $levy->unit }}
                                            @endif
                                        @endif
                                    </td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $levy->rate_type)) }}</td>
                                    <td>
                                        @if($levy->tariff_chapter_id)
                                            @php $levyChapter = $chapters->firstWhere('id', $levy->tariff_chapter_id); @endphp
                                            @if($levyChapter)
                                                <span class="badge bg-primary">Ch. {{ $levyChapter->chapter_number }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">All</span>
                                        @endif
                                    </td>
                                    <td>{{ $levy->legal_reference ?? '-' }}</td>
                                    <td>
                                        @if($levy->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- Reference Data Tab -->
        <div class="tab-pane fade" id="reference" role="tabpanel">
            @if($referenceData->isEmpty())
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No reference data found for this country. Import data via CLI or admin tools.
                </div>
            @else
                <div class="row">
                    <div class="col-md-3">
                        <!-- Navigation for types -->
                        <div class="nav flex-column nav-pills me-3" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                            @foreach($referenceData as $type => $records)
                                <button class="nav-link d-flex justify-content-between align-items-center {{ $loop->first ? 'active' : '' }}" 
                                    id="v-pills-{{ $type }}-tab" 
                                    data-bs-toggle="pill" 
                                    data-bs-target="#v-pills-{{ $type }}" 
                                    type="button" 
                                    role="tab">
                                    {{ ucfirst(str_replace('_', ' ', $type)) }}
                                    <span class="badge bg-light text-dark ms-2">{{ $records->count() }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-9">
                        <div class="tab-content" id="v-pills-tabContent">
                            @foreach($referenceData as $type => $records)
                                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="v-pills-{{ $type }}" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">{{ ucfirst(str_replace('_', ' ', $type)) }} Reference Data</h5>
                                            <span class="badge bg-secondary">{{ $records->count() }} records</span>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                                                <table class="table table-hover table-striped mb-0">
                                                    <thead class="table-light sticky-top">
                                                        <tr>
                                                            <th style="width: 100px;">Code</th>
                                                            <th>Label/Name</th>
                                                            <th>Local Matches</th>
                                                            <th style="width: 100px;">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($records as $record)
                                                            <tr>
                                                                <td>
                                                                    <code class="fw-bold">{{ $record->code }}</code>
                                                                    @if($record->is_default)
                                                                        <span class="badge bg-info ms-1" title="Default Value">Def</span>
                                                                    @endif
                                                                </td>
                                                                <td>{{ $record->label }}</td>
                                                                <td>
                                                                    @if($record->local_matches)
                                                                        @foreach($record->local_matches as $match)
                                                                            <span class="badge bg-light text-dark border me-1">{{ $match }}</span>
                                                                        @endforeach
                                                                    @else
                                                                        <span class="text-muted small">-</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @if($record->is_active)
                                                                        <span class="badge bg-success">Active</span>
                                                                    @else
                                                                        <span class="badge bg-secondary">Inactive</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chapter search functionality
    const searchInput = document.getElementById('chapterSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.chapter-item').forEach(function(item) {
                const searchText = item.getAttribute('data-search');
                if (searchText.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
    
    // Expand/Collapse all chapters
    const expandAll = document.getElementById('expandAllChapters');
    const collapseAll = document.getElementById('collapseAllChapters');
    
    if (expandAll) {
        expandAll.addEventListener('click', function() {
            document.querySelectorAll('#chaptersAccordion .accordion-collapse').forEach(function(collapse) {
                new bootstrap.Collapse(collapse, {toggle: false}).show();
            });
        });
    }
    
    if (collapseAll) {
        collapseAll.addEventListener('click', function() {
            document.querySelectorAll('#chaptersAccordion .accordion-collapse').forEach(function(collapse) {
                new bootstrap.Collapse(collapse, {toggle: false}).hide();
            });
        });
    }
});
</script>
@endpush

<style>
.note-text {
    font-size: 0.9rem;
    line-height: 1.6;
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 0.375rem;
    border-left: 3px solid #dee2e6;
}

.accordion-button:not(.collapsed) {
    background-color: #e8f4fc;
    color: #0c63e4;
}
</style>
@endsection
