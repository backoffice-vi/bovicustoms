@extends('layouts.app')

@section('title', 'Edit ' . $webFormTarget->name)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.web-form-targets.index') }}">Web Form Targets</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.web-form-targets.show', $webFormTarget) }}">{{ $webFormTarget->name }}</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 mt-2">Edit {{ $webFormTarget->name }}</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <form action="{{ route('admin.web-form-targets.update', $webFormTarget) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Basic Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Target Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name', $webFormTarget->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="code" class="form-label">Code</label>
                                <input type="text" class="form-control @error('code') is-invalid @enderror" 
                                       id="code" name="code" value="{{ old('code', $webFormTarget->code) }}">
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
                                    <option value="{{ $country->id }}" {{ old('country_id', $webFormTarget->country_id) == $country->id ? 'selected' : '' }}>
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
                                       id="base_url" name="base_url" value="{{ old('base_url', $webFormTarget->base_url) }}" required>
                                @error('base_url')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="login_url" class="form-label">Login URL Path <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('login_url') is-invalid @enderror" 
                                       id="login_url" name="login_url" value="{{ old('login_url', $webFormTarget->login_url) }}" required>
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
                                <option value="form" {{ old('auth_type', $webFormTarget->auth_type) === 'form' ? 'selected' : '' }}>Form Login</option>
                                <option value="oauth" {{ old('auth_type', $webFormTarget->auth_type) === 'oauth' ? 'selected' : '' }}>OAuth</option>
                                <option value="api_key" {{ old('auth_type', $webFormTarget->auth_type) === 'api_key' ? 'selected' : '' }}>API Key</option>
                                <option value="none" {{ old('auth_type', $webFormTarget->auth_type) === 'none' ? 'selected' : '' }}>None</option>
                            </select>
                            @error('auth_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div id="form-auth-fields" style="{{ $webFormTarget->auth_type !== 'form' ? 'display:none' : '' }}">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="{{ old('username', $credentials['username'] ?? '') }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password"
                                           placeholder="Leave blank to keep current">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="username_field" class="form-label">Username Field Selector</label>
                                    <input type="text" class="form-control" id="username_field" name="username_field" 
                                           value="{{ old('username_field', $credentials['username_field'] ?? 'input[name=\"username\"]') }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="password_field" class="form-label">Password Field Selector</label>
                                    <input type="text" class="form-control" id="password_field" name="password_field" 
                                           value="{{ old('password_field', $credentials['password_field'] ?? 'input[name=\"password\"]') }}">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="submit_selector" class="form-label">Submit Button Selector</label>
                                    <input type="text" class="form-control" id="submit_selector" name="submit_selector" 
                                           value="{{ old('submit_selector', $credentials['submit_selector'] ?? 'button[type=\"submit\"]') }}">
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
                                   {{ old('requires_ai', $webFormTarget->requires_ai) ? 'checked' : '' }}>
                            <label class="form-check-label" for="requires_ai">
                                <strong>Use AI-Assisted Submission</strong>
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                   {{ old('is_active', $webFormTarget->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                <strong>Active</strong>
                            </label>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $webFormTarget->notes) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('admin.web-form-targets.show', $webFormTarget) }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Cancel
                    </a>
                    <div>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Target</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong>{{ $webFormTarget->name }}</strong>?</p>
                <p class="text-danger">This will also delete all pages, field mappings, and submission history.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('admin.web-form-targets.destroy', $webFormTarget) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('auth_type').addEventListener('change', function() {
    document.getElementById('form-auth-fields').style.display = this.value === 'form' ? 'block' : 'none';
});
</script>
@endpush
@endsection
