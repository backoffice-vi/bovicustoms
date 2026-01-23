@extends('layouts.app')

@section('title', 'Edit Exemption - Admin')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.exemptions.index') }}">Exemptions</a></li>
                <li class="breadcrumb-item active">Edit {{ $exemption->name }}</li>
            </ol>
        </nav>
        <h1 class="h3">
            <i class="fas fa-edit me-2"></i>Edit Exemption Category
        </h1>
    </div>

    <form method="POST" action="{{ route('admin.exemptions.update', $exemption) }}">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Basic Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" value="{{ $exemption->country->name }}" disabled>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $exemption->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description', $exemption->description) }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Legal Reference</label>
                            <input type="text" name="legal_reference" class="form-control" 
                                   value="{{ old('legal_reference', $exemption->legal_reference) }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Applies to Code Patterns</label>
                            <input type="text" name="applies_to_patterns" class="form-control" 
                                   value="{{ old('applies_to_patterns', $exemption->applies_to_patterns ? implode(', ', $exemption->applies_to_patterns) : '') }}">
                            <div class="form-text">Comma-separated patterns. Use * as wildcard.</div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" 
                                           {{ old('is_active', $exemption->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Valid From</label>
                                <input type="date" name="valid_from" class="form-control" 
                                       value="{{ old('valid_from', $exemption->valid_from?->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Valid Until</label>
                                <input type="date" name="valid_until" class="form-control" 
                                       value="{{ old('valid_until', $exemption->valid_until?->format('Y-m-d')) }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update Exemption
                    </button>
                    <a href="{{ route('admin.exemptions.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
