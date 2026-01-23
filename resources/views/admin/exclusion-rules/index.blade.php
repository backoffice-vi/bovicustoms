@extends('layouts.app')

@section('title', 'Exclusion Rules - Admin')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-ban me-2"></i>Classification Exclusion Rules
        </h1>
        <div>
            <a href="{{ route('admin.exclusion-rules.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Add Rule
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
                    <label class="form-label">Source Chapter</label>
                    <select name="chapter_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Chapters</option>
                        @foreach($chapters as $chapter)
                            <option value="{{ $chapter->id }}" {{ $chapterId == $chapter->id ? 'selected' : '' }}>
                                Chapter {{ $chapter->chapter_number }} - {{ Str::limit($chapter->title, 40) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    @if($countryId)
                        <form method="POST" action="{{ route('admin.exclusion-rules.parse') }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="country_id" value="{{ $countryId }}">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-magic me-1"></i> Parse Rules from Notes
                            </button>
                        </form>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Rules Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Source Chapter</th>
                        <th>Pattern</th>
                        <th>Target</th>
                        <th>Rule</th>
                        <th>Priority</th>
                        <th width="100">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($exclusions as $exclusion)
                        <tr>
                            <td>
                                <span class="badge bg-primary">
                                    Ch. {{ $exclusion->sourceChapter?->chapter_number }}
                                </span>
                                <small class="text-muted d-block">{{ Str::limit($exclusion->sourceChapter?->title, 30) }}</small>
                            </td>
                            <td>
                                <code>{{ $exclusion->exclusion_pattern }}</code>
                            </td>
                            <td>
                                @if($exclusion->targetChapter)
                                    <span class="badge bg-success">
                                        Ch. {{ $exclusion->targetChapter->chapter_number }}
                                    </span>
                                @elseif($exclusion->target_heading)
                                    <span class="badge bg-info">{{ $exclusion->target_heading }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <small>{{ Str::limit($exclusion->rule_text, 60) }}</small>
                                @if($exclusion->source_note_reference)
                                    <br><small class="text-muted">{{ $exclusion->source_note_reference }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $exclusion->priority }}</span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.exclusion-rules.edit', $exclusion) }}" class="btn btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.exclusion-rules.destroy', $exclusion) }}" class="d-inline" onsubmit="return confirm('Delete this rule?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p>No exclusion rules found</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($exclusions->hasPages())
            <div class="card-footer">
                {{ $exclusions->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
