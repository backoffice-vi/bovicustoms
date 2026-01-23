@extends('layouts.app')

@section('title', 'Add Exclusion Rule - Admin')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.exclusion-rules.index') }}">Exclusion Rules</a></li>
                <li class="breadcrumb-item active">Add New</li>
            </ol>
        </nav>
        <h1 class="h3">
            <i class="fas fa-plus-circle me-2"></i>Add Exclusion Rule
        </h1>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.exclusion-rules.store') }}">
                        @csrf

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Country <span class="text-danger">*</span></label>
                                <select name="country_id" class="form-select @error('country_id') is-invalid @enderror" required>
                                    <option value="">Select Country</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                            {{ $country->flag_emoji }} {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('country_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Source Chapter <span class="text-danger">*</span></label>
                                <select name="source_chapter_id" class="form-select @error('source_chapter_id') is-invalid @enderror" required>
                                    <option value="">Select Chapter</option>
                                    @foreach($chapters as $chapter)
                                        <option value="{{ $chapter->id }}" {{ old('source_chapter_id') == $chapter->id ? 'selected' : '' }}>
                                            Chapter {{ $chapter->chapter_number }} - {{ Str::limit($chapter->title, 40) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('source_chapter_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Exclusion Pattern <span class="text-danger">*</span></label>
                            <input type="text" name="exclusion_pattern" class="form-control @error('exclusion_pattern') is-invalid @enderror" 
                                   value="{{ old('exclusion_pattern') }}" placeholder="e.g., ceramic*, glass items" required>
                            <div class="form-text">Use * as wildcard. Items matching this pattern will be redirected.</div>
                            @error('exclusion_pattern')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Target Chapter</label>
                                <select name="target_chapter_id" class="form-select @error('target_chapter_id') is-invalid @enderror">
                                    <option value="">None</option>
                                    @foreach($chapters as $chapter)
                                        <option value="{{ $chapter->id }}" {{ old('target_chapter_id') == $chapter->id ? 'selected' : '' }}>
                                            Chapter {{ $chapter->chapter_number }} - {{ Str::limit($chapter->title, 40) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('target_chapter_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Target Heading (optional)</label>
                                <input type="text" name="target_heading" class="form-control @error('target_heading') is-invalid @enderror" 
                                       value="{{ old('target_heading') }}" placeholder="e.g., 69.03">
                                @error('target_heading')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rule Description <span class="text-danger">*</span></label>
                            <textarea name="rule_text" class="form-control @error('rule_text') is-invalid @enderror" rows="3" required>{{ old('rule_text') }}</textarea>
                            <div class="form-text">Human-readable explanation of this exclusion rule.</div>
                            @error('rule_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Source Note Reference</label>
                                <input type="text" name="source_note_reference" class="form-control @error('source_note_reference') is-invalid @enderror" 
                                       value="{{ old('source_note_reference') }}" placeholder="e.g., Chapter 84, Note 1(b)">
                                @error('source_note_reference')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Priority</label>
                                <input type="number" name="priority" class="form-control @error('priority') is-invalid @enderror" 
                                       value="{{ old('priority', 5) }}" min="0" max="100">
                                <div class="form-text">Higher = applied first</div>
                                @error('priority')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Create Rule
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
