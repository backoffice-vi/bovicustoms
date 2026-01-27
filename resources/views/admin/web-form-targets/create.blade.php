@extends('layouts.app')

@section('title', 'Add Web Form Target')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.web-form-targets.index') }}">Web Form Targets</a></li>
                    <li class="breadcrumb-item active">Add New</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 mt-2">Add Web Form Target</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <form action="{{ route('admin.web-form-targets.store') }}" method="POST">
                @csrf

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Basic Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Target Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name') }}" required
                                       placeholder="e.g., CAPS BVI Customs Portal">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="code" class="form-label">Code (optional)</label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                       id="code" name="code" value="{{ old('code') }}"
                                       placeholder="e.g., caps_bvi">
                                <small class="text-muted">Auto-generated if empty</small>
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="country_id" class="form-label">Country <span class="text-danger">*</span></label>
                            <select class="form-select @error('country_id') is-invalid @enderror" 
                                    id="country_id" name="country_id" required>
                                <option value="">Select Country...</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}" {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                        {{ $country->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('country_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="base_url" class="form-label">Base URL <span class="text-danger">*</span></label>
                                <input type="url" class="form-control @error('base_url') is-invalid @enderror" 
                                       id="base_url" name="base_url" value="{{ old('base_url') }}" required
                                       placeholder="https://caps.gov.vg">
                                @error('base_url')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="login_url" class="form-label">Login URL Path <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('login_url') is-invalid @enderror" 
                                       id="login_url" name="login_url" value="{{ old('login_url', '/') }}" required
                                       placeholder="/CAPSWeb/">
                                @error('login_url')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Authentication</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="auth_type" class="form-label">Authentication Type <span class="text-danger">*</span></label>
                            <select class="form-select @error('auth_type') is-invalid @enderror" 
                                    id="auth_type" name="auth_type" required>
                                <option value="form" {{ old('auth_type', 'form') === 'form' ? 'selected' : '' }}>Form Login</option>
                                <option value="oauth" {{ old('auth_type') === 'oauth' ? 'selected' : '' }}>OAuth</option>
                                <option value="api_key" {{ old('auth_type') === 'api_key' ? 'selected' : '' }}>API Key</option>
                                <option value="none" {{ old('auth_type') === 'none' ? 'selected' : '' }}>None</option>
                            </select>
                            @error('auth_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div id="form-auth-fields">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control @error('username') is-invalid @enderror" 
                                           id="username" name="username" value="{{ old('username') }}"
                                           placeholder="Portal username">
                                    @error('username')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                           id="password" name="password"
                                           placeholder="Portal password">
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="username_field" class="form-label">Username Field Selector</label>
                                    <input type="text" class="form-control @error('username_field') is-invalid @enderror" 
                                           id="username_field" name="username_field" 
                                           value="{{ old('username_field', 'input[name="username"]') }}"
                                           placeholder='input[name="username"]'>
                                    @error('username_field')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="password_field" class="form-label">Password Field Selector</label>
                                    <input type="text" class="form-control @error('password_field') is-invalid @enderror" 
                                           id="password_field" name="password_field" 
                                           value="{{ old('password_field', 'input[name="password"]') }}"
                                           placeholder='input[name="password"]'>
                                    @error('password_field')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="submit_selector" class="form-label">Submit Button Selector</label>
                                    <input type="text" class="form-control @error('submit_selector') is-invalid @enderror" 
                                           id="submit_selector" name="submit_selector" 
                                           value="{{ old('submit_selector', 'button[type="submit"]') }}"
                                           placeholder='button[type="submit"]'>
                                    @error('submit_selector')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Options</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="requires_ai" name="requires_ai" value="1"
                                   {{ old('requires_ai', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="requires_ai">
                                <strong>Use AI-Assisted Submission</strong>
                                <br>
                                <small class="text-muted">Enable Claude AI to handle errors, find elements, and interpret results</small>
                            </label>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" 
                                      id="notes" name="notes" rows="3"
                                      placeholder="Any additional notes about this target...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('admin.web-form-targets.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Create Target
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
                    <h6>What is a Web Form Target?</h6>
                    <p class="text-muted small">
                        A web form target represents an external customs portal where declarations can be submitted automatically.
                    </p>

                    <h6>Setting Up</h6>
                    <ol class="text-muted small">
                        <li>Enter the portal's base URL and login path</li>
                        <li>Configure authentication credentials</li>
                        <li>After creating, add pages and field mappings</li>
                        <li>Test the connection before using</li>
                    </ol>

                    <h6>CSS Selectors</h6>
                    <p class="text-muted small">
                        Use CSS selectors to identify form elements:
                    </p>
                    <ul class="text-muted small">
                        <li><code>#id</code> - by ID</li>
                        <li><code>.class</code> - by class</li>
                        <li><code>[name="x"]</code> - by name attribute</li>
                        <li><code>input[type="text"]</code> - by type</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('auth_type').addEventListener('change', function() {
    const formFields = document.getElementById('form-auth-fields');
    formFields.style.display = this.value === 'form' ? 'block' : 'none';
});
</script>
@endpush
@endsection
