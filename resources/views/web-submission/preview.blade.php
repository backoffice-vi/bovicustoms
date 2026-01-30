@extends('layouts.app')

@section('title', 'Preview Web Submission - ' . $target->name)

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            {{-- Header --}}
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-globe me-2 text-primary"></i>Preview Web Submission
                    </h1>
                    <p class="text-muted mb-0">
                        Review the data before submitting to {{ $target->name }}
                    </p>
                </div>
                <div>
                    <a href="{{ route('declaration-forms.show', $declaration) }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Declaration
                    </a>
                </div>
            </div>

            {{-- AI Assistance Status --}}
            @if(!empty($preview['ai_assisted']))
                <div class="alert alert-info mb-4">
                    <h5 class="alert-heading"><i class="fas fa-robot me-2"></i>Claude AI Assisted Mapping</h5>
                    <p class="mb-0">
                        Claude AI analyzed your declaration and intelligently mapped the data to CAPS fields.
                        @if(!empty($preview['ai_notes']))
                            <br><small class="text-muted">{{ $preview['ai_notes'] }}</small>
                        @endif
                    </p>
                </div>
            @endif

            {{-- Validation Status --}}
            @php
                $isValid = $validation['valid'] ?? false;
                $errors = $validation['errors'] ?? [];
                $warnings = $validation['warnings'] ?? [];
            @endphp
            @if(!$isValid)
                <div class="alert alert-warning mb-4">
                    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Validation Issues</h5>
                    <ul class="mb-0">
                        @foreach($errors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    @if(!empty($warnings))
                        <hr>
                        <p class="mb-0"><strong>Warnings:</strong></p>
                        <ul class="mb-0">
                            @foreach($warnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @elseif(!empty($warnings))
                <div class="alert alert-info mb-4">
                    <h5 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Warnings</h5>
                    <ul class="mb-0">
                        @foreach($warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle me-2"></i>All validations passed. Ready to submit.
                </div>
            @endif

            <div class="row">
                {{-- Declaration Summary --}}
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Declaration Summary</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <th>Reference</th>
                                    <td>{{ $declaration->reference_number }}</td>
                                </tr>
                                <tr>
                                    <th>Country</th>
                                    <td>{{ $declaration->country->name ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Invoice</th>
                                    <td>{{ $declaration->invoice->invoice_number ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>Items</th>
                                    <td>{{ is_countable($declaration->items) ? count($declaration->items) : 0 }}</td>
                                </tr>
                                <tr>
                                    <th>Total Value</th>
                                    <td>${{ number_format($declaration->invoice->total_amount ?? 0, 2) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Target Info --}}
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Target Portal</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <th>Portal</th>
                                    <td>{{ $target->name }}</td>
                                </tr>
                                <tr>
                                    <th>URL</th>
                                    <td>
                                        <a href="{{ $target->base_url }}" target="_blank" class="text-truncate d-inline-block" style="max-width: 200px;">
                                            {{ $target->base_url }}
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Pages</th>
                                    <td>{{ $target->pages ? $target->pages->count() : 0 }}</td>
                                </tr>
                                <tr>
                                    <th>Fields</th>
                                    <td>{{ $target->pages ? $target->pages->sum(fn($p) => $p->fieldMappings ? $p->fieldMappings->count() : 0) : 0 }}</td>
                                </tr>
                                <tr>
                                    <th>AI Mode</th>
                                    <td>
                                        @if($target->requires_ai)
                                            <span class="badge bg-purple">AI Assisted</span>
                                        @else
                                            <span class="badge bg-secondary">Standard</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Submit Actions --}}
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Submit</h5>
                        </div>
                        <div class="card-body d-flex flex-column">
                            @php
                                $isCaps = str_contains(strtolower($target->base_url ?? ''), 'caps.gov.vg') || 
                                          str_contains(strtolower($target->name ?? ''), 'caps');
                            @endphp
                            
                            @if($isCaps)
                                <p class="text-muted">
                                    <strong>Claude AI</strong> will intelligently fill the CAPS form.
                                    Choose <strong>Save</strong> to create a draft TD for review,
                                    or <strong>Submit</strong> to submit for customs processing.
                                </p>
                            @else
                                <p class="text-muted">
                                    Click submit to begin the automated form submission process.
                                    The system will fill out the online forms using the mapped data.
                                </p>
                            @endif
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="use_ai" id="useAi" checked>
                                <label class="form-check-label" for="useAi">
                                    <i class="fas fa-robot me-1 text-info"></i>
                                    Use AI-assisted form filling
                                </label>
                            </div>

                            @if(!$isValid)
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="force" id="forceSubmit">
                                    <label class="form-check-label text-warning" for="forceSubmit">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Submit anyway (ignore validation errors)
                                    </label>
                                </div>
                            @endif

                            @if($isCaps)
                                <div class="d-grid gap-2 mt-auto">
                                    <form action="{{ route('web-submission.submit', ['declaration' => $declaration, 'target' => $target]) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="action" value="save">
                                        <input type="hidden" name="use_ai" value="1">
                                        @if(!$isValid)<input type="hidden" name="force" value="1">@endif
                                        <button type="submit" class="btn btn-warning btn-lg w-100 mb-2" id="saveBtn">
                                            <i class="fas fa-save me-2"></i>Save (Draft)
                                        </button>
                                    </form>
                                    <form action="{{ route('web-submission.submit', ['declaration' => $declaration, 'target' => $target]) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="action" value="submit">
                                        <input type="hidden" name="use_ai" value="1">
                                        @if(!$isValid)<input type="hidden" name="force" value="1">@endif
                                        <button type="submit" class="btn btn-success btn-lg w-100" id="submitBtn">
                                            <i class="fas fa-paper-plane me-2"></i>Submit to Customs
                                        </button>
                                    </form>
                                </div>
                            @else
                                <form action="{{ route('web-submission.submit', ['declaration' => $declaration, 'target' => $target]) }}" method="POST" class="mt-auto">
                                    @csrf
                                    <input type="hidden" name="use_ai" id="useAiHidden" value="1">
                                    <button type="submit" class="btn btn-success btn-lg w-100" {{ !$isValid ? 'disabled' : '' }} id="submitBtn">
                                        <i class="fas fa-paper-plane me-2"></i>Submit to Portal
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Field Mappings Preview --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Field Mappings Preview</h5>
                </div>
                <div class="card-body">
                    @if(!empty($preview['pages']))
                        <div class="accordion" id="pagesAccordion">
                            @foreach($preview['pages'] as $pageIndex => $page)
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button {{ $pageIndex > 0 ? 'collapsed' : '' }}" type="button" 
                                                data-bs-toggle="collapse" data-bs-target="#page{{ $pageIndex }}">
                                            <i class="fas fa-file me-2"></i>
                                            {{ $page['page_name'] ?? $page['name'] ?? 'Page ' . ($pageIndex + 1) }}
                                            <span class="badge bg-secondary ms-2">{{ count($page['fields'] ?? []) }} fields</span>
                                        </button>
                                    </h2>
                                    <div id="page{{ $pageIndex }}" class="accordion-collapse collapse {{ $pageIndex === 0 ? 'show' : '' }}" 
                                         data-bs-parent="#pagesAccordion">
                                        <div class="accordion-body">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width: 25%">Field</th>
                                                            <th style="width: 20%">Source</th>
                                                            <th style="width: 35%">Value</th>
                                                            <th style="width: 20%">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @forelse($page['fields'] ?? [] as $field)
                                                            <tr>
                                                                <td>
                                                                    <strong>{{ $field['label'] ?? $field['name'] ?? 'Unknown' }}</strong>
                                                                    @if(!empty($field['required']))
                                                                        <span class="text-danger">*</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @if(!empty($field['source']) && str_contains($field['source'], 'AI'))
                                                                        <span class="badge bg-info">
                                                                            <i class="fas fa-robot me-1"></i>AI
                                                                        </span>
                                                                    @else
                                                                        <code class="small">{{ Str::limit($field['source'] ?? $field['selector'] ?? 'N/A', 25) }}</code>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @if(isset($field['value']) && $field['value'] !== null && $field['value'] !== '')
                                                                        @if(is_array($field['value']))
                                                                            <span class="text-muted">[Array: {{ count($field['value']) }} items]</span>
                                                                        @else
                                                                            {{ Str::limit($field['value'], 50) }}
                                                                        @endif
                                                                    @else
                                                                        <span class="text-muted">—</span>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @if(!empty($field['error']))
                                                                        <span class="badge bg-danger">
                                                                            <i class="fas fa-times me-1"></i>{{ $field['error'] }}
                                                                        </span>
                                                                    @elseif((empty($field['value']) || $field['value'] === '') && !empty($field['required']))
                                                                        <span class="badge bg-warning text-dark">
                                                                            <i class="fas fa-exclamation me-1"></i>Missing
                                                                        </span>
                                                                    @elseif(!empty($field['value']))
                                                                        <span class="badge bg-success">
                                                                            <i class="fas fa-check me-1"></i>Ready
                                                                        </span>
                                                                    @else
                                                                        <span class="badge bg-secondary">
                                                                            <i class="fas fa-minus me-1"></i>Optional
                                                                        </span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @empty
                                                            <tr>
                                                                <td colspan="4" class="text-center text-muted py-3">
                                                                    No field mappings defined for this page.
                                                                </td>
                                                            </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No field mappings found. Please configure the target portal's field mappings first.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const forceCheckbox = document.getElementById('forceSubmit');
    const submitBtn = document.getElementById('submitBtn');
    
    if (forceCheckbox && submitBtn) {
        forceCheckbox.addEventListener('change', function() {
            submitBtn.disabled = !this.checked;
        });
    }
});
</script>
@endpush
