@extends('layouts.app')

@section('title', 'Add Exemption - Admin')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.exemptions.index') }}">Exemptions</a></li>
                <li class="breadcrumb-item active">Add New</li>
            </ol>
        </nav>
        <h1 class="h3">
            <i class="fas fa-plus-circle me-2"></i>Add Exemption Category
        </h1>
    </div>

    <form method="POST" action="{{ route('admin.exemptions.store') }}">
        @csrf

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Basic Information</h5>
                    </div>
                    <div class="card-body">
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
                                <label class="form-label">Legal Reference</label>
                                <input type="text" name="legal_reference" class="form-control" 
                                       value="{{ old('legal_reference') }}" placeholder="e.g., Schedule 5, Para 19">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name') }}" placeholder="e.g., Computer hardware" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Applies to Code Patterns</label>
                            <input type="text" name="applies_to_patterns" class="form-control" 
                                   value="{{ old('applies_to_patterns') }}" placeholder="e.g., 8471*, 8473*">
                            <div class="form-text">Comma-separated patterns. Use * as wildcard.</div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" 
                                           {{ old('is_active', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Valid From</label>
                                <input type="date" name="valid_from" class="form-control" value="{{ old('valid_from') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Valid Until</label>
                                <input type="date" name="valid_until" class="form-control" value="{{ old('valid_until') }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Conditions</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addCondition()">
                            <i class="fas fa-plus"></i> Add Condition
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="conditions-container">
                            <div class="text-muted text-center py-3" id="no-conditions">
                                No conditions added yet. Click "Add Condition" to add requirements.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Create Exemption
                    </button>
                    <a href="{{ route('admin.exemptions.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>

<template id="condition-template">
    <div class="condition-row border rounded p-3 mb-3">
        <div class="row">
            <div class="col-md-4 mb-2">
                <label class="form-label">Type</label>
                <select name="conditions[INDEX][type]" class="form-select" required>
                    @foreach($conditionTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-2">
                <label class="form-label">Description</label>
                <input type="text" name="conditions[INDEX][description]" class="form-control" required>
            </div>
            <div class="col-md-2 mb-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeCondition(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="col-md-10 mb-2">
                <label class="form-label">Requirement Details</label>
                <textarea name="conditions[INDEX][requirement]" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-2 mb-2 d-flex align-items-center">
                <div class="form-check">
                    <input type="hidden" name="conditions[INDEX][mandatory]" value="0">
                    <input type="checkbox" name="conditions[INDEX][mandatory]" value="1" class="form-check-input" checked>
                    <label class="form-check-label">Mandatory</label>
                </div>
            </div>
        </div>
    </div>
</template>

@push('scripts')
<script>
    let conditionIndex = 0;

    function addCondition() {
        const container = document.getElementById('conditions-container');
        const noConditions = document.getElementById('no-conditions');
        const template = document.getElementById('condition-template');
        
        if (noConditions) {
            noConditions.remove();
        }

        const html = template.innerHTML.replace(/INDEX/g, conditionIndex);
        container.insertAdjacentHTML('beforeend', html);
        conditionIndex++;
    }

    function removeCondition(btn) {
        btn.closest('.condition-row').remove();
    }
</script>
@endpush
@endsection
