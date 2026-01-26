@extends('layouts.app')

@section('title', 'Tariff Notes - Tariff Database')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-sticky-note me-2"></i>Tariff Notes
            </h1>
            <p class="text-muted mb-0">
                {{ number_format($stats['chapter_notes']) }} chapter notes + {{ number_format($stats['section_notes']) }} section notes
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
                <div class="col-md-2">
                    <label class="form-label">Note Type</label>
                    <select name="note_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="general" {{ $noteType == 'general' ? 'selected' : '' }}>General</option>
                        <option value="exclusion" {{ $noteType == 'exclusion' ? 'selected' : '' }}>Exclusion</option>
                        <option value="definition" {{ $noteType == 'definition' ? 'selected' : '' }}>Definition</option>
                        <option value="subheading_note" {{ $noteType == 'subheading_note' ? 'selected' : '' }}>Subheading Note</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search Text</label>
                    <input type="text" name="search" class="form-control" placeholder="Search in note text..." value="{{ $search }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                    <a href="{{ route('admin.tariff-database.notes') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Section Notes (if any) -->
    @if($sectionNotes->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Section Notes ({{ $sectionNotes->count() }})</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100px;">Section</th>
                                <th style="width: 80px;">Note #</th>
                                <th style="width: 120px;">Type</th>
                                <th>Note Text</th>
                                <th style="width: 80px;">Country</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sectionNotes as $note)
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">Section {{ $note->section?->section_number }}</span>
                                    </td>
                                    <td>{{ $note->note_number ?? '-' }}</td>
                                    <td>
                                        <span class="badge 
                                            @if($note->note_type === 'exclusion') bg-danger
                                            @elseif($note->note_type === 'definition') bg-info
                                            @else bg-secondary
                                            @endif">
                                            {{ ucfirst(str_replace('_', ' ', $note->note_type ?? 'general')) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="note-text-cell" style="white-space: pre-wrap; max-height: 200px; overflow-y: auto;">{{ $note->note_text }}</div>
                                    </td>
                                    <td>
                                        @if($note->section?->country)
                                            {{ $note->section->country->flag_emoji ?? '' }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Chapter Notes -->
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-book me-2"></i>Chapter Notes</h5>
            <span class="badge bg-light text-primary">{{ number_format($chapterNotes->total()) }} notes</span>
        </div>
        <div class="card-body p-0">
            @if($chapterNotes->isEmpty())
                <div class="alert alert-info m-3">
                    <i class="fas fa-info-circle me-2"></i>
                    No chapter notes found matching your criteria.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100px;">Chapter</th>
                                <th style="width: 80px;">Note #</th>
                                <th style="width: 120px;">Type</th>
                                <th>Note Text</th>
                                <th style="width: 80px;">Country</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($chapterNotes as $note)
                                <tr>
                                    <td>
                                        <span class="badge bg-primary">Ch. {{ $note->chapter?->chapter_number }}</span>
                                        <br>
                                        <small class="text-muted" title="{{ $note->chapter?->title }}">{{ Str::limit($note->chapter?->title, 15) }}</small>
                                    </td>
                                    <td>{{ $note->note_number ?? '-' }}</td>
                                    <td>
                                        <span class="badge 
                                            @if($note->note_type === 'exclusion') bg-danger
                                            @elseif($note->note_type === 'definition') bg-info
                                            @elseif($note->note_type === 'subheading_note') bg-warning text-dark
                                            @else bg-secondary
                                            @endif">
                                            {{ ucfirst(str_replace('_', ' ', $note->note_type ?? 'general')) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="note-text-cell" style="white-space: pre-wrap; max-height: 200px; overflow-y: auto;">{{ $note->note_text }}</div>
                                    </td>
                                    <td>
                                        @if($note->chapter?->country)
                                            {{ $note->chapter->country->flag_emoji ?? '' }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if($chapterNotes->hasPages())
            <div class="card-footer">
                {{ $chapterNotes->links() }}
            </div>
        @endif
    </div>
</div>

<style>
.note-text-cell {
    font-size: 0.9rem;
    line-height: 1.5;
    background: #f8f9fa;
    padding: 0.5rem;
    border-radius: 0.25rem;
    border-left: 3px solid #dee2e6;
}
</style>
@endsection
