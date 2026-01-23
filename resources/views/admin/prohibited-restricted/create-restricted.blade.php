@extends('layouts.app')

@section('title', 'Add Restricted Good - Admin')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.prohibited-restricted.index') }}">Prohibited & Restricted</a></li>
                <li class="breadcrumb-item active">Add Restricted Good</li>
            </ol>
        </nav>
        <h1 class="h3">
            <i class="fas fa-lock me-2 text-warning"></i>Add Restricted Good
        </h1>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.prohibited-restricted.store-restricted') }}">
                        @csrf

                        <div class="mb-3">
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

                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name') }}" placeholder="e.g., Firearms" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Restriction Type</label>
                                <select name="restriction_type" class="form-select">
                                    @foreach($restrictionTypes as $value => $label)
                                        <option value="{{ $value }}" {{ old('restriction_type') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Permit Authority</label>
                                <input type="text" name="permit_authority" class="form-control" 
                                       value="{{ old('permit_authority') }}" placeholder="e.g., Commissioner of Police">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Requirements</label>
                            <textarea name="requirements" class="form-control" rows="3" 
                                      placeholder="Describe the requirements for importing this item">{{ old('requirements') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Detection Keywords</label>
                            <input type="text" name="detection_keywords" class="form-control" 
                                   value="{{ old('detection_keywords') }}" placeholder="firearms, guns, ammunition">
                            <div class="form-text">Comma-separated keywords for automatic detection.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save me-1"></i> Add Restricted Good
                            </button>
                            <a href="{{ route('admin.prohibited-restricted.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
