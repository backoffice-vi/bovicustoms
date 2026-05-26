@extends('layouts.app')

@section('title', 'Bulk Add Rate Overrides - Admin')

@section('content')
<div class="container-fluid py-4">
    <h2 class="mb-4"><i class="fas fa-layer-group me-2"></i>Bulk Add Rate Overrides</h2>
    <p class="text-muted">Apply the same override rate and effective window to many customs codes at once.</p>

    @if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.customs-code-rate-overrides.bulk.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Country <span class="text-danger">*</span></label>
                        <select name="country_id" class="form-select" required>
                            <option value="">— Select country —</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->id }}"
                                    {{ old('country_id', $selectedCountryId) == $country->id ? 'selected' : '' }}>
                                    {{ $country->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Override Rate (%) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.001" min="0" max="1000" name="override_rate" class="form-control" required
                                   value="{{ old('override_rate') }}">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Mode <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="mode" id="mode-codes" value="codes" {{ old('mode', 'codes') === 'codes' ? 'checked' : '' }}>
                            <label class="btn btn-outline-primary" for="mode-codes">By Code List</label>

                            <input type="radio" class="btn-check" name="mode" id="mode-chapter" value="chapter" {{ old('mode') === 'chapter' ? 'checked' : '' }}>
                            <label class="btn btn-outline-primary" for="mode-chapter">By Chapter Prefix</label>
                        </div>
                    </div>

                    <div class="col-12" id="codes-block">
                        <label class="form-label">Customs Codes</label>
                        <textarea name="codes" class="form-control" rows="5"
                                  placeholder="One code per line, or comma-separated. Accepts dotted (1006.20) or 7-digit (1006200) forms.">{{ old('codes') }}</textarea>
                        <div class="form-text">Each entry must match an existing row in <code>customs_codes</code>. Codes that don't match are silently skipped.</div>
                    </div>

                    <div class="col-md-4 d-none" id="chapter-block">
                        <label class="form-label">Chapter Prefix</label>
                        <input type="text" name="chapter" class="form-control" maxlength="4"
                               placeholder="e.g. 10 (whole chapter) or 1006 (heading)" value="{{ old('chapter') }}">
                        <div class="form-text">2 digits = chapter; 4 digits = HS heading.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Effective From <span class="text-danger">*</span></label>
                        <input type="date" name="effective_from" class="form-control" required
                               value="{{ old('effective_from') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Effective Until</label>
                        <input type="date" name="effective_until" class="form-control"
                               value="{{ old('effective_until') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Source / Legal Reference</label>
                        <input type="text" name="source" class="form-control" maxlength="255"
                               placeholder="e.g. BVI Notice 2026/05 — Cost-of-living basket"
                               value="{{ old('source') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="1000">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-bolt me-1"></i>Apply Bulk Override
                    </button>
                    <a href="{{ route('admin.customs-code-rate-overrides.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const codesBlock = document.getElementById('codes-block');
    const chapterBlock = document.getElementById('chapter-block');
    const radios = document.querySelectorAll('input[name="mode"]');

    function update() {
        const mode = document.querySelector('input[name="mode"]:checked')?.value || 'codes';
        if (mode === 'codes') {
            codesBlock.classList.remove('d-none');
            chapterBlock.classList.add('d-none');
        } else {
            codesBlock.classList.add('d-none');
            chapterBlock.classList.remove('d-none');
        }
    }

    radios.forEach(r => r.addEventListener('change', update));
    update();
})();
</script>
@endpush
@endsection
