@extends('layouts.app')

@section('title', 'Edit Country Levy - Admin')

@section('content')
<div class="container-fluid py-4">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.country-levies.index') }}">Country Levies</a></li>
                <li class="breadcrumb-item active">Edit {{ $countryLevy->levy_code }}</li>
            </ol>
        </nav>
        <h2 class="mb-1"><i class="fas fa-edit me-2"></i>Edit Levy: {{ $countryLevy->levy_name }}</h2>
        <p class="text-muted mb-0">Modify levy configuration for {{ $countryLevy->country?->name }}</p>
    </div>

    @if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('admin.country-levies.update', $countryLevy) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <strong>Levy Details</strong>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Country <span class="text-danger">*</span></label>
                                <select name="country_id" class="form-select @error('country_id') is-invalid @enderror" required>
                                    <option value="">Select Country...</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" {{ old('country_id', $countryLevy->country_id) == $country->id ? 'selected' : '' }}>
                                            {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('country_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Levy Code <span class="text-danger">*</span></label>
                                <input type="text" name="levy_code" class="form-control @error('levy_code') is-invalid @enderror" 
                                       value="{{ old('levy_code', $countryLevy->levy_code) }}" required maxlength="20">
                                @error('levy_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Levy Name <span class="text-danger">*</span></label>
                            <input type="text" name="levy_name" class="form-control @error('levy_name') is-invalid @enderror" 
                                   value="{{ old('levy_name', $countryLevy->levy_name) }}" required>
                            @error('levy_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $countryLevy->description) }}</textarea>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Rate <span class="text-danger">*</span></label>
                                <input type="number" step="0.0001" name="rate" class="form-control @error('rate') is-invalid @enderror" 
                                       value="{{ old('rate', $countryLevy->rate) }}" required min="0">
                                @error('rate')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Rate Type <span class="text-danger">*</span></label>
                                <select name="rate_type" class="form-select @error('rate_type') is-invalid @enderror" required>
                                    <option value="percentage" {{ old('rate_type', $countryLevy->rate_type) == 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                                    <option value="fixed_amount" {{ old('rate_type', $countryLevy->rate_type) == 'fixed_amount' ? 'selected' : '' }}>Fixed Amount ($)</option>
                                    <option value="per_unit" {{ old('rate_type', $countryLevy->rate_type) == 'per_unit' ? 'selected' : '' }}>Per Unit</option>
                                </select>
                                @error('rate_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Calculation Basis <span class="text-danger">*</span></label>
                                <select name="calculation_basis" class="form-select @error('calculation_basis') is-invalid @enderror" required>
                                    <option value="fob" {{ old('calculation_basis', $countryLevy->calculation_basis) == 'fob' ? 'selected' : '' }}>FOB Value</option>
                                    <option value="cif" {{ old('calculation_basis', $countryLevy->calculation_basis) == 'cif' ? 'selected' : '' }}>CIF Value</option>
                                    <option value="duty" {{ old('calculation_basis', $countryLevy->calculation_basis) == 'duty' ? 'selected' : '' }}>Customs Duty Amount</option>
                                    <option value="quantity" {{ old('calculation_basis', $countryLevy->calculation_basis) == 'quantity' ? 'selected' : '' }}>Quantity</option>
                                    <option value="weight" {{ old('calculation_basis', $countryLevy->calculation_basis) == 'weight' ? 'selected' : '' }}>Weight (kg)</option>
                                </select>
                                @error('calculation_basis')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3" id="unitField" style="display: none;">
                            <label class="form-label">Unit (for per-unit rates)</label>
                            <input type="text" name="unit" class="form-control" value="{{ old('unit', $countryLevy->unit) }}" placeholder="e.g., kg, item, container">
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <strong>Application Rules</strong>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="applies_to_all_tariffs" value="1" class="form-check-input" id="appliesToAll"
                                       {{ old('applies_to_all_tariffs', $countryLevy->applies_to_all_tariffs) ? 'checked' : '' }}>
                                <label class="form-check-label" for="appliesToAll">
                                    Applies to all tariff codes
                                </label>
                            </div>
                        </div>

                        <div class="mb-3" id="chaptersField" style="display: none;">
                            <label class="form-label">Applicable Tariff Chapters</label>
                            <input type="text" name="applicable_tariff_chapters" class="form-control" 
                                   value="{{ old('applicable_tariff_chapters', $countryLevy->applicable_tariff_chapters ? implode(', ', $countryLevy->applicable_tariff_chapters) : '') }}" 
                                   placeholder="e.g., 01, 02, 03 (comma-separated)">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Exempt Tariff Codes</label>
                            <input type="text" name="exempt_tariff_codes" class="form-control" 
                                   value="{{ old('exempt_tariff_codes', $countryLevy->exempt_tariff_codes ? implode(', ', $countryLevy->exempt_tariff_codes) : '') }}" 
                                   placeholder="e.g., 9801*, 9802.00 (comma-separated)">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Exempt Organization Types</label>
                            <input type="text" name="exempt_organization_types" class="form-control" 
                                   value="{{ old('exempt_organization_types', $countryLevy->exempt_organization_types ? implode(', ', $countryLevy->exempt_organization_types) : '') }}" 
                                   placeholder="e.g., government, non_profit (comma-separated)">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <strong>Status & Dates</strong>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive"
                                       {{ old('is_active', $countryLevy->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">Active</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Display Order</label>
                            <input type="number" name="display_order" class="form-control" 
                                   value="{{ old('display_order', $countryLevy->display_order) }}" min="0">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Effective From</label>
                            <input type="date" name="effective_from" class="form-control" 
                                   value="{{ old('effective_from', $countryLevy->effective_from?->format('Y-m-d')) }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Effective Until</label>
                            <input type="date" name="effective_until" class="form-control" 
                                   value="{{ old('effective_until', $countryLevy->effective_until?->format('Y-m-d')) }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Legal Reference</label>
                            <input type="text" name="legal_reference" class="form-control" 
                                   value="{{ old('legal_reference', $countryLevy->legal_reference) }}">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
                <a href="{{ route('admin.country-levies.index', ['country_id' => $countryLevy->country_id]) }}" class="btn btn-outline-secondary w-100">Cancel</a>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const rateType = document.querySelector('select[name="rate_type"]');
    const unitField = document.getElementById('unitField');
    const appliesToAll = document.getElementById('appliesToAll');
    const chaptersField = document.getElementById('chaptersField');

    function toggleUnitField() {
        unitField.style.display = rateType.value === 'per_unit' ? 'block' : 'none';
    }

    function toggleChaptersField() {
        chaptersField.style.display = appliesToAll.checked ? 'none' : 'block';
    }

    rateType.addEventListener('change', toggleUnitField);
    appliesToAll.addEventListener('change', toggleChaptersField);

    toggleUnitField();
    toggleChaptersField();
});
</script>
@endpush
@endsection
