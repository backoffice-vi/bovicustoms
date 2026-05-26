@csrf

@php
    $existingCode = $override->customsCode ?? null;
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Country <span class="text-danger">*</span></label>
        <select name="country_id" class="form-select" required>
            <option value="">— Select country —</option>
            @foreach($countries as $country)
                <option value="{{ $country->id }}"
                    {{ old('country_id', $override->country_id ?? $selectedCountryId ?? '') == $country->id ? 'selected' : '' }}>
                    {{ $country->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6">
        <label class="form-label">Override Rate (%) <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="number" step="0.001" min="0" max="1000" name="override_rate" class="form-control" required
                   value="{{ old('override_rate', $override->override_rate ?? '') }}">
            <span class="input-group-text">%</span>
        </div>
        <div class="form-text">Stored as a percentage (e.g. <code>5.000</code> = 5%).</div>
    </div>

    <div class="col-12">
        <label class="form-label">Customs Code <span class="text-danger">*</span></label>
        <input type="hidden" name="customs_code_id" id="customs_code_id"
               value="{{ old('customs_code_id', $override->customs_code_id ?? '') }}">
        <input type="text" id="customs_code_search" class="form-control"
               placeholder="Type at least 2 characters of the code or description"
               autocomplete="off"
               value="{{ $existingCode ? $existingCode->code . ' — ' . \Illuminate\Support\Str::limit($existingCode->description ?? '', 80) : '' }}">
        <div id="customs_code_results" class="list-group position-relative" style="z-index: 5;"></div>
        @if($existingCode)
            <div class="form-text">Current base rate: <strong>{{ number_format((float) $existingCode->duty_rate, 2) }}%</strong></div>
        @endif
    </div>

    <div class="col-md-6">
        <label class="form-label">Effective From <span class="text-danger">*</span></label>
        <input type="date" name="effective_from" class="form-control" required
               value="{{ old('effective_from', isset($override) ? $override->effective_from?->toDateString() : '') }}">
    </div>

    <div class="col-md-6">
        <label class="form-label">Effective Until</label>
        <input type="date" name="effective_until" class="form-control"
               value="{{ old('effective_until', isset($override) ? $override->effective_until?->toDateString() : '') }}">
        <div class="form-text">Leave blank for open-ended.</div>
    </div>

    <div class="col-12">
        <label class="form-label">Source / Legal Reference</label>
        <input type="text" name="source" class="form-control" maxlength="255"
               placeholder="e.g. BVI Customs Notice 2026/05 — Basket of essential goods"
               value="{{ old('source', $override->source ?? '') }}">
    </div>

    <div class="col-12">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="3" maxlength="1000">{{ old('notes', $override->notes ?? '') }}</textarea>
    </div>

    <div class="col-12">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
                   {{ old('is_active', isset($override) ? $override->is_active : true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>{{ isset($override) ? 'Update' : 'Create' }} Override
    </button>
    <a href="{{ route('admin.customs-code-rate-overrides.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

@push('scripts')
<script>
(function () {
    const search = document.getElementById('customs_code_search');
    const hidden = document.getElementById('customs_code_id');
    const results = document.getElementById('customs_code_results');
    const url = "{{ route('admin.customs-code-rate-overrides.search-codes') }}";
    let debounce;

    function clearResults() { results.innerHTML = ''; }

    search.addEventListener('input', function () {
        clearTimeout(debounce);
        const term = this.value.trim();
        hidden.value = '';
        if (term.length < 2) { clearResults(); return; }

        debounce = setTimeout(async function () {
            try {
                const r = await fetch(url + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                clearResults();
                data.forEach(function (item) {
                    const a = document.createElement('a');
                    a.href = '#';
                    a.className = 'list-group-item list-group-item-action';
                    a.innerHTML = '<code>' + item.code + '</code> — ' +
                        (item.description ? item.description.substring(0, 100) : '') +
                        ' <span class="badge bg-secondary float-end">' +
                        (item.duty_rate !== null ? Number(item.duty_rate).toFixed(2) + '%' : '—') +
                        '</span>';
                    a.addEventListener('click', function (e) {
                        e.preventDefault();
                        hidden.value = item.id;
                        search.value = item.code + ' — ' + (item.description || '').substring(0, 100);
                        clearResults();
                    });
                    results.appendChild(a);
                });
            } catch (e) { /* swallow */ }
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (!results.contains(e.target) && e.target !== search) clearResults();
    });
})();
</script>
@endpush
