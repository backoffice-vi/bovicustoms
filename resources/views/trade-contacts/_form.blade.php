{{-- Shared form fields for create/edit --}}

<!-- Contact Type & Basic Info -->
<div class="row mb-4">
    <div class="col-12">
        <h5 class="text-primary mb-3"><i class="fas fa-id-card me-2"></i>Basic Information</h5>
    </div>
    
    <div class="col-md-6 mb-3">
        <label for="contact_type" class="form-label">Contact Type <span class="text-danger">*</span></label>
        <select name="contact_type" id="contact_type" class="form-select @error('contact_type') is-invalid @enderror" required>
            <option value="">Select Type...</option>
            @foreach($contactTypes as $type => $label)
                <option value="{{ $type }}" {{ old('contact_type', $contact?->contact_type ?? $preselectedType ?? '') === $type ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('contact_type')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
        <input type="text" name="company_name" id="company_name" 
               class="form-control @error('company_name') is-invalid @enderror"
               value="{{ old('company_name', $contact?->company_name) }}" required>
        @error('company_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="contact_name" class="form-label">Contact Person</label>
        <input type="text" name="contact_name" id="contact_name" 
               class="form-control @error('contact_name') is-invalid @enderror"
               value="{{ old('contact_name', $contact?->contact_name) }}">
        @error('contact_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <div class="form-check mt-4">
            <input type="checkbox" name="is_default" id="is_default" value="1" 
                   class="form-check-input" {{ old('is_default', $contact?->is_default) ? 'checked' : '' }}>
            <label for="is_default" class="form-check-label">
                <i class="fas fa-star text-warning me-1"></i>Set as default for this contact type
            </label>
        </div>
    </div>
</div>

<!-- Address -->
<div class="row mb-4">
    <div class="col-12">
        <h5 class="text-primary mb-3"><i class="fas fa-map-marker-alt me-2"></i>Address</h5>
    </div>
    
    <div class="col-12 mb-3">
        <label for="address_line_1" class="form-label">Address Line 1</label>
        <input type="text" name="address_line_1" id="address_line_1" 
               class="form-control @error('address_line_1') is-invalid @enderror"
               value="{{ old('address_line_1', $contact?->address_line_1) }}"
               placeholder="Street address">
        @error('address_line_1')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 mb-3">
        <label for="address_line_2" class="form-label">Address Line 2</label>
        <input type="text" name="address_line_2" id="address_line_2" 
               class="form-control @error('address_line_2') is-invalid @enderror"
               value="{{ old('address_line_2', $contact?->address_line_2) }}"
               placeholder="Suite, unit, building, etc.">
        @error('address_line_2')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="city" class="form-label">City</label>
        <input type="text" name="city" id="city" 
               class="form-control @error('city') is-invalid @enderror"
               value="{{ old('city', $contact?->city) }}">
        @error('city')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="state_province" class="form-label">State / Province</label>
        <input type="text" name="state_province" id="state_province" 
               class="form-control @error('state_province') is-invalid @enderror"
               value="{{ old('state_province', $contact?->state_province) }}">
        @error('state_province')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="postal_code" class="form-label">Postal Code</label>
        <input type="text" name="postal_code" id="postal_code" 
               class="form-control @error('postal_code') is-invalid @enderror"
               value="{{ old('postal_code', $contact?->postal_code) }}">
        @error('postal_code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="country_id" class="form-label">Country</label>
        <select name="country_id" id="country_id" class="form-select @error('country_id') is-invalid @enderror">
            <option value="">Select Country...</option>
            @foreach($countries as $country)
                <option value="{{ $country->id }}" {{ old('country_id', $contact?->country_id) == $country->id ? 'selected' : '' }}>
                    {{ $country->name }}
                </option>
            @endforeach
        </select>
        @error('country_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Communication -->
<div class="row mb-4">
    <div class="col-12">
        <h5 class="text-primary mb-3"><i class="fas fa-phone-alt me-2"></i>Communication</h5>
    </div>
    
    <div class="col-md-4 mb-3">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" name="phone" id="phone" 
               class="form-control @error('phone') is-invalid @enderror"
               value="{{ old('phone', $contact?->phone) }}">
        @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="fax" class="form-label">Fax</label>
        <input type="text" name="fax" id="fax" 
               class="form-control @error('fax') is-invalid @enderror"
               value="{{ old('fax', $contact?->fax) }}">
        @error('fax')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" name="email" id="email" 
               class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $contact?->email) }}">
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Identifiers -->
<div class="row mb-4">
    <div class="col-12">
        <h5 class="text-primary mb-3"><i class="fas fa-id-badge me-2"></i>Identifiers</h5>
    </div>
    
    <div class="col-md-6 mb-3">
        <label for="tax_id" class="form-label">Tax ID / VAT Number</label>
        <input type="text" name="tax_id" id="tax_id" 
               class="form-control @error('tax_id') is-invalid @enderror"
               value="{{ old('tax_id', $contact?->tax_id) }}">
        @error('tax_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="license_number" class="form-label">License Number</label>
        <input type="text" name="license_number" id="license_number" 
               class="form-control @error('license_number') is-invalid @enderror"
               value="{{ old('license_number', $contact?->license_number) }}"
               placeholder="Import/Export License, Broker License, etc.">
        @error('license_number')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Banking (for banks or payment info) -->
<div class="row mb-4" id="banking-section">
    <div class="col-12">
        <h5 class="text-primary mb-3"><i class="fas fa-university me-2"></i>Banking Information</h5>
        <p class="text-muted small">Optional - used for payment sections on customs forms</p>
    </div>
    
    <div class="col-md-4 mb-3">
        <label for="bank_name" class="form-label">Bank Name</label>
        <input type="text" name="bank_name" id="bank_name" 
               class="form-control @error('bank_name') is-invalid @enderror"
               value="{{ old('bank_name', $contact?->bank_name) }}">
        @error('bank_name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="bank_account" class="form-label">Account Number</label>
        <input type="text" name="bank_account" id="bank_account" 
               class="form-control @error('bank_account') is-invalid @enderror"
               value="{{ old('bank_account', $contact?->bank_account) }}">
        @error('bank_account')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="bank_routing" class="form-label">Routing / SWIFT Code</label>
        <input type="text" name="bank_routing" id="bank_routing" 
               class="form-control @error('bank_routing') is-invalid @enderror"
               value="{{ old('bank_routing', $contact?->bank_routing) }}">
        @error('bank_routing')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<!-- Notes -->
<div class="row">
    <div class="col-12">
        <h5 class="text-primary mb-3"><i class="fas fa-sticky-note me-2"></i>Notes</h5>
    </div>
    
    <div class="col-12 mb-3">
        <label for="notes" class="form-label">Additional Notes</label>
        <textarea name="notes" id="notes" rows="3" 
                  class="form-control @error('notes') is-invalid @enderror"
                  placeholder="Any additional information about this contact...">{{ old('notes', $contact?->notes) }}</textarea>
        @error('notes')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
