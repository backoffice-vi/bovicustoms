@extends('layouts.app')

@section('title', 'Add Country - Admin')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.countries.index') }}">Countries</a></li>
                <li class="breadcrumb-item active">Add New</li>
            </ol>
        </nav>
        <h1 class="h3">
            <i class="fas fa-plus-circle me-2"></i>Add Country
        </h1>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.countries.store') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="code" class="form-label">Country Code <span class="text-danger">*</span></label>
                                    <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" 
                                           value="{{ old('code') }}" placeholder="e.g., VGB" required maxlength="3" style="text-transform: uppercase;">
                                    <div class="form-text">ISO 3166-1 alpha-3 code (3 letters)</div>
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="currency_code" class="form-label">Currency Code <span class="text-danger">*</span></label>
                                    <input type="text" name="currency_code" id="currency_code" class="form-control @error('currency_code') is-invalid @enderror" 
                                           value="{{ old('currency_code') }}" placeholder="e.g., USD" required maxlength="3" style="text-transform: uppercase;">
                                    <div class="form-text">ISO 4217 currency code (3 letters)</div>
                                    @error('currency_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Country Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name') }}" placeholder="e.g., British Virgin Islands" required maxlength="255">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="flag_emoji" class="form-label">Flag Emoji</label>
                            <input type="text" name="flag_emoji" id="flag_emoji" class="form-control @error('flag_emoji') is-invalid @enderror" 
                                   value="{{ old('flag_emoji') }}" placeholder="e.g., 🇻🇬" maxlength="10">
                            <div class="form-text">Optional: Country flag emoji for display</div>
                            @error('flag_emoji')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                                <div class="form-text">Inactive countries will not be available for selection</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="{{ route('admin.countries.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Country
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>Help</h5>
                    <p class="card-text">
                        <strong>Country Code:</strong> Use the ISO 3166-1 alpha-3 code (e.g., VGB for British Virgin Islands, USA for United States).
                    </p>
                    <p class="card-text">
                        <strong>Currency Code:</strong> Use the ISO 4217 code (e.g., USD for US Dollar, EUR for Euro).
                    </p>
                    <p class="card-text mb-0">
                        <strong>Flag Emoji:</strong> You can copy flag emojis from websites like emojipedia.org.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
