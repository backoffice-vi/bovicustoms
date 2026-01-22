@extends('layouts.app')

@section('title', 'Edit Customs Code - Admin')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.customs-codes.index') }}">Customs Codes</a></li>
                <li class="breadcrumb-item active">Edit {{ $customsCode->code }}</li>
            </ol>
        </nav>
        <h1 class="h3">
            <i class="fas fa-edit me-2"></i>Edit Customs Code
        </h1>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.customs-codes.update', $customsCode) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="country_id" class="form-label">Country <span class="text-danger">*</span></label>
                            <select name="country_id" id="country_id" class="form-select @error('country_id') is-invalid @enderror" required>
                                <option value="">Select a country</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}" {{ (old('country_id', $customsCode->country_id) == $country->id) ? 'selected' : '' }}>
                                        {{ $country->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('country_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="code" class="form-label">Customs Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" 
                                   value="{{ old('code', $customsCode->code) }}" placeholder="e.g., 0101.21.00" required maxlength="20">
                            <div class="form-text">Enter the HS code (e.g., 0101.21.00)</div>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror" required>{{ old('description', $customsCode->description) }}</textarea>
                            <div class="form-text">Full description of the goods covered by this code</div>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="duty_rate" class="form-label">Duty Rate (%) <span class="text-danger">*</span></label>
                                    <input type="number" name="duty_rate" id="duty_rate" class="form-control @error('duty_rate') is-invalid @enderror" 
                                           value="{{ old('duty_rate', $customsCode->duty_rate) }}" step="0.01" min="0" max="100" required>
                                    <div class="form-text">Enter the duty rate as a percentage (0-100)</div>
                                    @error('duty_rate')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hs_code_version" class="form-label">HS Code Version</label>
                                    <input type="text" name="hs_code_version" id="hs_code_version" class="form-control @error('hs_code_version') is-invalid @enderror" 
                                           value="{{ old('hs_code_version', $customsCode->hs_code_version) }}" placeholder="e.g., 2022" maxlength="10">
                                    <div class="form-text">Optional: HS Code version year</div>
                                    @error('hs_code_version')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="{{ route('admin.customs-codes.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Update Code
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- History Section (if there's history) -->
            @if($customsCode->history()->exists())
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Change History</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Field</th>
                                    <th>Old Value</th>
                                    <th>New Value</th>
                                    <th>Changed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customsCode->history()->limit(10)->get() as $history)
                                <tr>
                                    <td><small>{{ $history->created_at->format('M d, Y H:i') }}</small></td>
                                    <td><strong>{{ ucfirst(str_replace('_', ' ', $history->field_name)) }}</strong></td>
                                    <td><code>{{ Str::limit($history->old_value, 30) }}</code></td>
                                    <td><code>{{ Str::limit($history->new_value, 30) }}</code></td>
                                    <td>
                                        @if($history->changedBy)
                                            {{ $history->changedBy->name }}
                                        @elseif($history->lawDocument)
                                            <small class="text-muted">Via Law Document</small>
                                        @else
                                            <small class="text-muted">System</small>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>Help</h5>
                    <p class="card-text">
                        <strong>Customs Code:</strong> Also known as HS (Harmonized System) code, used internationally to classify goods.
                    </p>
                    <p class="card-text">
                        <strong>Duty Rate:</strong> The percentage of the item's value that must be paid as import duty.
                    </p>
                    <p class="card-text mb-0">
                        <strong>Version:</strong> The year of the HS code nomenclature (e.g., 2017, 2022).
                    </p>
                </div>
            </div>

            <div class="card mt-3 border-warning">
                <div class="card-body">
                    <h5 class="card-title text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Caution</h5>
                    <p class="card-text">
                        Changes to this customs code will affect all future classifications. Historical data will remain unchanged.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
