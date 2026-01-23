@extends('layouts.app')

@section('title', 'Edit Exclusion Rule - Admin')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.exclusion-rules.index') }}">Exclusion Rules</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
        <h1 class="h3">
            <i class="fas fa-edit me-2"></i>Edit Exclusion Rule
        </h1>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.exclusion-rules.update', $exclusionRule) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" value="{{ $exclusionRule->country->name }}" disabled>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Source Chapter</label>
                            <input type="text" class="form-control" value="Chapter {{ $exclusionRule->sourceChapter->chapter_number }} - {{ $exclusionRule->sourceChapter->title }}" disabled>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Exclusion Pattern <span class="text-danger">*</span></label>
                            <input type="text" name="exclusion_pattern" class="form-control @error('exclusion_pattern') is-invalid @enderror" 
                                   value="{{ old('exclusion_pattern', $exclusionRule->exclusion_pattern) }}" required>
                            @error('exclusion_pattern')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Target Chapter</label>
                                <select name="target_chapter_id" class="form-select">
                                    <option value="">None</option>
                                    @foreach($chapters as $chapter)
                                        <option value="{{ $chapter->id }}" {{ old('target_chapter_id', $exclusionRule->target_chapter_id) == $chapter->id ? 'selected' : '' }}>
                                            Chapter {{ $chapter->chapter_number }} - {{ Str::limit($chapter->title, 40) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Target Heading</label>
                                <input type="text" name="target_heading" class="form-control" 
                                       value="{{ old('target_heading', $exclusionRule->target_heading) }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rule Description <span class="text-danger">*</span></label>
                            <textarea name="rule_text" class="form-control" rows="3" required>{{ old('rule_text', $exclusionRule->rule_text) }}</textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Source Note Reference</label>
                                <input type="text" name="source_note_reference" class="form-control" 
                                       value="{{ old('source_note_reference', $exclusionRule->source_note_reference) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Priority</label>
                                <input type="number" name="priority" class="form-control" 
                                       value="{{ old('priority', $exclusionRule->priority) }}" min="0" max="100">
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Update Rule
                            </button>
                            <a href="{{ route('admin.exclusion-rules.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
