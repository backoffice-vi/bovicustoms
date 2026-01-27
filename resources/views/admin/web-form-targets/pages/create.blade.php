@extends('layouts.app')

@section('title', 'Add Page - ' . $webFormTarget->name)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.web-form-targets.index') }}">Web Form Targets</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.web-form-targets.show', $webFormTarget) }}">{{ $webFormTarget->name }}</a></li>
                    <li class="breadcrumb-item active">Add Page</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 mt-2">Add Page to {{ $webFormTarget->name }}</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <form action="{{ route('admin.web-form-targets.pages.store', $webFormTarget) }}" method="POST">
                @csrf

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Page Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="name" class="form-label">Page Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name') }}" required
                                       placeholder="e.g., Trade Declaration Entry">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="page_type" class="form-label">Page Type <span class="text-danger">*</span></label>
                                <select class="form-select @error('page_type') is-invalid @enderror" 
                                        id="page_type" name="page_type" required>
                                    <option value="login" {{ old('page_type') === 'login' ? 'selected' : '' }}>Login Page</option>
                                    <option value="form" {{ old('page_type', 'form') === 'form' ? 'selected' : '' }}>Form Page</option>
                                    <option value="confirmation" {{ old('page_type') === 'confirmation' ? 'selected' : '' }}>Confirmation Page</option>
                                    <option value="search" {{ old('page_type') === 'search' ? 'selected' : '' }}>Search Page</option>
                                    <option value="other" {{ old('page_type') === 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('page_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="url_pattern" class="form-label">URL Pattern <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $webFormTarget->base_url }}</span>
                                    <input type="text" class="form-control @error('url_pattern') is-invalid @enderror" 
                                           id="url_pattern" name="url_pattern" value="{{ old('url_pattern', '/') }}" required
                                           placeholder="/path/to/page">
                                </div>
                                @error('url_pattern')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="sequence_order" class="form-label">Sequence Order</label>
                                <input type="number" class="form-control @error('sequence_order') is-invalid @enderror" 
                                       id="sequence_order" name="sequence_order" value="{{ old('sequence_order') }}" min="1"
                                       placeholder="Auto">
                                <small class="text-muted">Order in workflow</small>
                                @error('sequence_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Selectors</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="submit_selector" class="form-label">Submit Button Selector</label>
                            <input type="text" class="form-control @error('submit_selector') is-invalid @enderror" 
                                   id="submit_selector" name="submit_selector" value="{{ old('submit_selector') }}"
                                   placeholder='button[type="submit"], input[value="Save"]'>
                            <small class="text-muted">CSS selector for the submit/continue button on this page</small>
                            @error('submit_selector')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="success_indicator" class="form-label">Success Indicator</label>
                            <input type="text" class="form-control @error('success_indicator') is-invalid @enderror" 
                                   id="success_indicator" name="success_indicator" value="{{ old('success_indicator') }}"
                                   placeholder='.success-message, text="Saved successfully"'>
                            <small class="text-muted">Selector or text that indicates success</small>
                            @error('success_indicator')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="error_indicator" class="form-label">Error Indicator</label>
                            <input type="text" class="form-control @error('error_indicator') is-invalid @enderror" 
                                   id="error_indicator" name="error_indicator" value="{{ old('error_indicator') }}"
                                   placeholder='.error-message, .alert-danger'>
                            <small class="text-muted">Selector that indicates an error occurred</small>
                            @error('error_indicator')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('admin.web-form-targets.show', $webFormTarget) }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Add Page
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Help</h5>
                </div>
                <div class="card-body">
                    <h6>Page Types</h6>
                    <ul class="small text-muted">
                        <li><strong>Login Page</strong> - Authentication page</li>
                        <li><strong>Form Page</strong> - Main data entry form</li>
                        <li><strong>Confirmation</strong> - Success/result page</li>
                        <li><strong>Search</strong> - Record lookup page</li>
                    </ul>

                    <h6>Sequence Order</h6>
                    <p class="small text-muted">
                        Pages are processed in sequence order. Login should be first (1),
                        followed by form pages, and confirmation last.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
