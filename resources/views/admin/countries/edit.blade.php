@extends('layouts.app')

@section('title', 'Edit Country - Admin')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.countries.index') }}">Countries</a></li>
                <li class="breadcrumb-item active">Edit {{ $country->name }}</li>
            </ol>
        </nav>
        <h1 class="h3">
            <i class="fas fa-edit me-2"></i>Edit Country
        </h1>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.countries.update', $country) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="code" class="form-label">Country Code <span class="text-danger">*</span></label>
                                    <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" 
                                           value="{{ old('code', $country->code) }}" placeholder="e.g., VGB" required maxlength="3" style="text-transform: uppercase;">
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
                                           value="{{ old('currency_code', $country->currency_code) }}" placeholder="e.g., USD" required maxlength="3" style="text-transform: uppercase;">
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
                                   value="{{ old('name', $country->name) }}" placeholder="e.g., British Virgin Islands" required maxlength="255">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="flag_emoji" class="form-label">Flag Emoji</label>
                            <input type="text" name="flag_emoji" id="flag_emoji" class="form-control @error('flag_emoji') is-invalid @enderror" 
                                   value="{{ old('flag_emoji', $country->flag_emoji) }}" placeholder="e.g., 🇻🇬" maxlength="10">
                            <div class="form-text">Optional: Country flag emoji for display</div>
                            @error('flag_emoji')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', $country->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                                <div class="form-text">Inactive countries will not be available for selection</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3"><i class="fas fa-shield-alt me-2"></i>Default Insurance Settings</h5>
                        <p class="text-muted small mb-3">Configure default insurance calculation for shipments to this country. Users can still override these settings per shipment.</p>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="default_insurance_method" class="form-label">Insurance Method</label>
                                    <select name="default_insurance_method" id="default_insurance_method" class="form-select @error('default_insurance_method') is-invalid @enderror">
                                        <option value="percentage" {{ old('default_insurance_method', $country->default_insurance_method) == 'percentage' ? 'selected' : '' }}>Percentage of FOB</option>
                                        <option value="manual" {{ old('default_insurance_method', $country->default_insurance_method) == 'manual' ? 'selected' : '' }}>Manual Entry</option>
                                        <option value="document" {{ old('default_insurance_method', $country->default_insurance_method) == 'document' ? 'selected' : '' }}>From Insurance Certificate</option>
                                    </select>
                                    <div class="form-text">How insurance should be calculated by default</div>
                                    @error('default_insurance_method')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="default_insurance_percentage" class="form-label">Insurance Rate (%)</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="default_insurance_percentage" id="default_insurance_percentage" 
                                               class="form-control @error('default_insurance_percentage') is-invalid @enderror" 
                                               value="{{ old('default_insurance_percentage', $country->default_insurance_percentage ?? '1.00') }}" 
                                               min="0" max="100" placeholder="1.00">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="form-text">Percentage of FOB value (common: 0.5% - 1.5%)</div>
                                    @error('default_insurance_percentage')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="{{ route('admin.countries.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Update Country
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

            <div class="card mt-3 border-warning">
                <div class="card-body">
                    <h5 class="card-title text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Caution</h5>
                    <p class="card-text mb-0">
                        Changes to this country may affect customs codes and other related data. Deactivating a country will hide it from selection in other parts of the system.
                    </p>
                </div>
            </div>

            @if($country->customsCodes()->count() > 0 || $country->organizations()->count() > 0)
            <div class="card mt-3 border-info">
                <div class="card-body">
                    <h5 class="card-title text-info"><i class="fas fa-link me-2"></i>Related Data</h5>
                    <ul class="mb-0">
                        @if($country->customsCodes()->count() > 0)
                            <li>{{ $country->customsCodes()->count() }} customs code(s)</li>
                        @endif
                        @if($country->organizations()->count() > 0)
                            <li>{{ $country->organizations()->count() }} organization(s)</li>
                        @endif
                    </ul>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
