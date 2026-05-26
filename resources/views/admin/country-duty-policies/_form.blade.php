@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Country <span class="text-danger">*</span></label>
        <select name="country_id" class="form-select" required>
            <option value="">— Select country —</option>
            @foreach($countries as $country)
                <option value="{{ $country->id }}"
                    {{ old('country_id', $policy->country_id ?? $selectedCountryId ?? '') == $country->id ? 'selected' : '' }}>
                    {{ $country->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Customs Duty Basis <span class="text-danger">*</span></label>
        <select name="basis" class="form-select" required>
            @foreach($basisOptions as $value => $label)
                <option value="{{ $value }}" {{ old('basis', $policy->basis ?? '') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Selects which value (FOB or CIF) is used as the base for Customs Duty during this window.</div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Effective From <span class="text-danger">*</span></label>
        <input type="date" name="effective_from" class="form-control" required
               value="{{ old('effective_from', isset($policy) ? $policy->effective_from?->toDateString() : '') }}">
    </div>

    <div class="col-md-6">
        <label class="form-label">Effective Until</label>
        <input type="date" name="effective_until" class="form-control"
               value="{{ old('effective_until', isset($policy) ? $policy->effective_until?->toDateString() : '') }}">
        <div class="form-text">Leave blank for open-ended.</div>
    </div>

    <div class="col-12">
        <label class="form-label">Source / Legal Reference</label>
        <input type="text" name="source" class="form-control" maxlength="255"
               placeholder="e.g. BVI Customs Notice 2026/05 — Temporary FOB-basis measure"
               value="{{ old('source', $policy->source ?? '') }}">
    </div>

    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="3" maxlength="1000"
                  placeholder="Optional internal notes (visible to admins only)">{{ old('notes', $policy->notes ?? '') }}</textarea>
    </div>

    <div class="col-12">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
                   {{ old('is_active', isset($policy) ? $policy->is_active : true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
        <div class="form-text">Inactive rows are ignored even if today's date is inside their window.</div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>{{ isset($policy) ? 'Update' : 'Create' }} Policy
    </button>
    <a href="{{ route('admin.country-duty-policies.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
