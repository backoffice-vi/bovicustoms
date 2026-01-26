@extends('layouts.app')

@section('title', 'Exclusion Rules - Tariff Database')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-ban me-2"></i>Exclusion Rules
            </h1>
            <p class="text-muted mb-0">
                {{ number_format($stats['exclusion_rules']) }} rules parsed from chapter notes
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
                <div class="col-md-4">
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
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search pattern or rule text..." value="{{ $search }}">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                    <a href="{{ route('admin.tariff-database.exclusions') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Exclusion Rules Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>
                Exclusion Rules
            </h5>
            <span class="badge bg-warning text-dark">{{ number_format($exclusions->total()) }} rules</span>
        </div>
        <div class="card-body p-0">
            @if($exclusions->isEmpty())
                <div class="alert alert-info m-3">
                    <i class="fas fa-info-circle me-2"></i>
                    No exclusion rules found. Exclusion rules are automatically parsed from chapter notes.
                    <br><br>
                    To generate exclusion rules:
                    <ol class="mb-0 mt-2">
                        <li>Ensure chapter notes are in the database (process a law document)</li>
                        <li>Go to <a href="{{ route('admin.exclusion-rules.index') }}">Exclusion Rules</a> admin page</li>
                        <li>Click "Parse Rules from Notes" to auto-generate rules</li>
                    </ol>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 120px;">Source Chapter</th>
                                <th>Exclusion Pattern</th>
                                <th style="width: 120px;">Target</th>
                                <th>Rule Text</th>
                                <th style="width: 150px;">Note Reference</th>
                                <th style="width: 80px;">Country</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($exclusions as $exclusion)
                                <tr>
                                    <td>
                                        @if($exclusion->sourceChapter)
                                            <span class="badge bg-primary">Ch. {{ $exclusion->sourceChapter->chapter_number }}</span>
                                            <br><small class="text-muted">{{ Str::limit($exclusion->sourceChapter->title, 15) }}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <code class="text-danger fs-6">{{ $exclusion->exclusion_pattern }}</code>
                                    </td>
                                    <td>
                                        @if($exclusion->targetChapter)
                                            <span class="badge bg-success">Ch. {{ $exclusion->targetChapter->chapter_number }}</span>
                                        @elseif($exclusion->target_heading)
                                            <span class="badge bg-info">{{ $exclusion->target_heading }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small style="white-space: pre-wrap;">{{ Str::limit($exclusion->rule_text, 200) }}</small>
                                    </td>
                                    <td>
                                        @if($exclusion->source_note_reference)
                                            <small class="text-muted">{{ $exclusion->source_note_reference }}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($exclusion->country)
                                            {{ $exclusion->country->flag_emoji ?? '' }}
                                            <small>{{ $exclusion->country->code }}</small>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if($exclusions->hasPages())
            <div class="card-footer">
                {{ $exclusions->links() }}
            </div>
        @endif
    </div>

    <!-- Explanation Card -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>What are Exclusion Rules?</h5>
        </div>
        <div class="card-body">
            <p>
                Exclusion rules are classification constraints parsed from chapter notes in the tariff schedule.
                They indicate that certain products should NOT be classified under a particular chapter, 
                and instead should be classified elsewhere.
            </p>
            <h6>Example:</h6>
            <div class="bg-light p-3 rounded">
                <p class="mb-1"><strong>Source Chapter:</strong> Chapter 84 (Machinery)</p>
                <p class="mb-1"><strong>Exclusion Pattern:</strong> "sewing machines"</p>
                <p class="mb-1"><strong>Target:</strong> Chapter 84.52</p>
                <p class="mb-0"><strong>Meaning:</strong> Sewing machines should be classified under 84.52, not other headings in Chapter 84</p>
            </div>
            <p class="mt-3 mb-0">
                These rules are used by the classification system to improve accuracy and avoid misclassification.
            </p>
        </div>
    </div>
</div>
@endsection
