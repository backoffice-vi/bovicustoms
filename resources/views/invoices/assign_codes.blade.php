@extends('layouts.app')

@section('title', 'Assign Customs Codes - BoVi Customs')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-tags me-2"></i>Assign Customs Codes</h2>
            <p class="text-muted mb-0">
                Review AI classifications and assign final HS codes
                @if(isset($country))
                    for <strong>{{ $country->name }}</strong>
                @endif
            </p>
        </div>
        <a href="{{ route('invoices.review') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Review
        </a>
    </div>

    {{-- Invoice Header Summary --}}
    @if(!empty($invoiceHeader))
    <div class="card mb-4 border-0 bg-light">
        <div class="card-body py-3">
            <div class="row">
                <div class="col-auto">
                    <small class="text-muted">Invoice:</small>
                    <strong>{{ $invoiceHeader['invoice_number'] ?? 'Draft' }}</strong>
                </div>
                @if($invoiceHeader['invoice_date'] ?? null)
                <div class="col-auto">
                    <small class="text-muted">Date:</small>
                    <strong>{{ \Carbon\Carbon::parse($invoiceHeader['invoice_date'])->format('M d, Y') }}</strong>
                </div>
                @endif
                <div class="col-auto">
                    <small class="text-muted">Items:</small>
                    <strong>{{ count($itemsWithCodes) }}</strong>
                </div>
            </div>
        </div>
    </div>
    @endif

    <form action="{{ route('invoices.finalize') }}" method="POST" id="assignCodesForm">
        @csrf
        @if(isset($invoice))
        <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
        @endif

        {{-- Items List --}}
        <div class="row">
                    @foreach($itemsWithCodes as $index => $item)
            @php
                $classification = $item['classification'] ?? [];
                $isSuccess = $classification['success'] ?? false;
                $isProhibited = $classification['prohibited'] ?? false;
                $isRestricted = $classification['restricted'] ?? false;
                $precedents = $item['precedents'] ?? [];
                $hasConflict = $item['has_conflict'] ?? false;
            @endphp
            <div class="col-12 mb-4">
                <div class="card {{ $isProhibited ? 'border-danger' : ($isRestricted ? 'border-warning' : ($hasConflict ? 'border-info' : '')) }}">
                    {{-- Item Header --}}
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-secondary me-3 fs-6">{{ $index + 1 }}</span>
                            <div>
                                <h6 class="mb-0">{{ $item['description'] }}</h6>
                                <small class="text-muted">
                                    @if($item['sku'])SKU: {{ $item['sku'] }} | @endif
                                    @if($item['item_number'])Item #: {{ $item['item_number'] }} | @endif
                                    Qty: {{ $item['quantity'] ?? 1 }} |
                                    Unit Price: ${{ number_format($item['unit_price'] ?? 0, 2) }}
                                </small>
                            </div>
                        </div>
                        <div>
                            @if($isProhibited)
                                <span class="badge bg-danger fs-6"><i class="fas fa-ban me-1"></i>PROHIBITED</span>
                            @elseif($isRestricted)
                                <span class="badge bg-warning text-dark fs-6"><i class="fas fa-exclamation-triangle me-1"></i>RESTRICTED</span>
                            @elseif($isSuccess)
                                @php
                                    $confidence = $classification['confidence'] ?? 0;
                                    $confidenceClass = $confidence >= 80 ? 'success' : ($confidence >= 60 ? 'warning' : 'danger');
                                @endphp
                                <span class="badge bg-{{ $confidenceClass }} fs-6">
                                    <i class="fas fa-robot me-1"></i>{{ $confidence }}% Confidence
                                </span>
                            @else
                                <span class="badge bg-secondary fs-6"><i class="fas fa-question me-1"></i>Manual Entry Required</span>
                            @endif
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            {{-- Left Column: Classification Result --}}
                            <div class="col-lg-6">
                                {{-- Prohibited Warning --}}
                                @if($isProhibited)
                                <div class="alert alert-danger">
                                    <h6 class="alert-heading"><i class="fas fa-ban me-2"></i>Item Prohibited for Import</h6>
                                    <p class="mb-2">This item matches prohibited goods and cannot be imported.</p>
                                    @if(!empty($classification['prohibited_items']))
                                    <hr>
                                    <ul class="mb-0 small">
                                        @foreach($classification['prohibited_items'] as $prohibited)
                                        <li>
                                            <strong>{{ $prohibited['name'] }}</strong>
                                            @if($prohibited['legal_reference'])
                                                <br><em>{{ $prohibited['legal_reference'] }}</em>
                                            @endif
                                        </li>
                                        @endforeach
                                    </ul>
                                    @endif
                                </div>
                                @endif

                                {{-- Restricted Warning --}}
                                @if($isRestricted && !empty($classification['restricted_items']))
                                <div class="alert alert-warning">
                                    <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Import Restrictions Apply</h6>
                                    <ul class="mb-0 small">
                                        @foreach($classification['restricted_items'] as $restricted)
                                        <li>
                                            <strong>{{ $restricted['name'] }}</strong>
                                            @if($restricted['restriction_type'])
                                                - {{ $restricted['restriction_type'] }}
                                            @endif
                                            @if($restricted['permit_authority'])
                                                <br><em>Permit: {{ $restricted['permit_authority'] }}</em>
                                            @endif
                                        </li>
                                        @endforeach
                                    </ul>
                                </div>
                                @endif

                                {{-- Classification Success --}}
                                @if($isSuccess && !$isProhibited)
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">AI Recommended Code</label>
                                    <div class="d-flex align-items-center">
                                        <code class="fs-4 me-3 text-primary">{{ $classification['code'] }}</code>
                                        <div class="flex-grow-1">
                                            <small class="text-muted">{{ $classification['description'] ?? '' }}</small>
                                        </div>
                                    </div>
                                </div>

                                {{-- Confidence Bar --}}
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Classification Confidence</label>
                                    @php $confidence = $classification['confidence'] ?? 0; @endphp
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-{{ $confidence >= 80 ? 'success' : ($confidence >= 60 ? 'warning' : 'danger') }}" 
                                             role="progressbar" 
                                             style="width: {{ $confidence }}%"
                                             aria-valuenow="{{ $confidence }}" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            {{ $confidence }}%
                                        </div>
                                    </div>
                                </div>

                                {{-- AI Explanation --}}
                                @if(!empty($classification['explanation']))
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">AI Explanation</label>
                                    <div class="bg-light p-3 rounded small">
                                        <i class="fas fa-lightbulb text-warning me-2"></i>
                                        {{ $classification['explanation'] }}
                                    </div>
                                </div>
                                @endif

                                {{-- Duty Calculation --}}
                                @if(!empty($classification['duty_calculation']))
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Duty Information</label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="border rounded p-2 text-center">
                                                <small class="text-muted d-block">Duty Rate</small>
                                                <strong class="fs-5">{{ $classification['duty_rate'] ?? 'N/A' }}%</strong>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="border rounded p-2 text-center">
                                                <small class="text-muted d-block">Unit</small>
                                                <strong>{{ $classification['unit_of_measurement'] ?? 'N/A' }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                {{-- Available Exemptions --}}
                                @if(!empty($classification['exemptions_available']))
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="fas fa-certificate text-success me-1"></i>Available Exemptions
                                    </label>
                                    <div class="list-group list-group-flush small">
                                        @foreach($classification['exemptions_available'] as $exemption)
                                        <div class="list-group-item px-0">
                                            <strong>{{ $exemption['name'] }}</strong>
                                            @if($exemption['description'])
                                                <br><span class="text-muted">{{ $exemption['description'] }}</span>
                                            @endif
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                @elseif(!$isProhibited)
                                {{-- Classification Failed --}}
                                <div class="alert alert-secondary">
                                    <h6 class="alert-heading"><i class="fas fa-exclamation-circle me-2"></i>Classification Could Not Be Determined</h6>
                                    <p class="mb-2">{{ $classification['error'] ?? 'No matching customs codes found for this item.' }}</p>
                                    
                                    {{-- Classification Path (Debug) --}}
                                    @if(!empty($classification['classification_path']))
                                    <details class="mt-2">
                                        <summary class="small text-muted cursor-pointer">View classification attempts</summary>
                                        <ul class="small mt-2 mb-0">
                                            @foreach($classification['classification_path'] as $step)
                                            <li>{{ $step }}</li>
                                            @endforeach
                                        </ul>
                                    </details>
                                    @endif
                                </div>
                                <p class="text-muted small">Please search for or manually enter an HS code below.</p>
                                @endif
                            </div>

                            {{-- Right Column: Historical & Manual Selection --}}
                            <div class="col-lg-6">
                                {{-- Historical Precedents --}}
                                @if(!empty($precedents))
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="fas fa-history text-info me-1"></i>
                                        Historical Classifications
                                        @if($hasConflict)
                                            <span class="badge bg-info ms-2">Differs from AI</span>
                                        @endif
                                    </label>
                                    <div class="list-group">
                                        @foreach($precedents as $pIndex => $precedent)
                                        <label class="list-group-item list-group-item-action d-flex align-items-center {{ $pIndex === 0 && $hasConflict ? 'list-group-item-info' : '' }}">
                                            <input type="radio" 
                                                   class="form-check-input me-3 code-selection" 
                                                   name="items[{{ $index }}][code_source]" 
                                                   value="precedent_{{ $pIndex }}"
                                                   data-code="{{ $precedent['hs_code'] }}"
                                                   data-target="code_{{ $index }}">
                                            <div class="flex-grow-1">
                                                <code class="fw-bold">{{ $precedent['hs_code'] }}</code>
                                                <small class="d-block text-muted">{{ Str::limit($precedent['description'], 60) }}</small>
                                                <small class="text-muted">Used: {{ $precedent['created_at'] }}</small>
                                            </div>
                                            @if($pIndex === 0 && $hasConflict)
                                                <span class="badge bg-info">Previous</span>
                                            @endif
                                        </label>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                {{-- Code Selection --}}
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        <i class="fas fa-barcode me-1"></i>Final HS Code <span class="text-danger">*</span>
                                    </label>
                                    
                                    {{-- AI Recommended Option --}}
                                    @if($isSuccess && !$isProhibited)
                                    <div class="form-check mb-2">
                                        <input type="radio" 
                                               class="form-check-input code-selection" 
                                               name="items[{{ $index }}][code_source]" 
                                               value="ai"
                                               data-code="{{ $classification['code'] }}"
                                               data-target="code_{{ $index }}"
                                               id="ai_{{ $index }}"
                                               checked>
                                        <label class="form-check-label" for="ai_{{ $index }}">
                                            Use AI Recommendation: <code class="fw-bold">{{ $classification['code'] }}</code>
                                        </label>
                                    </div>
                                    @endif

                                    {{-- Alternative Codes --}}
                                    @if(!empty($classification['alternatives']))
                                    <div class="mb-2">
                                        <small class="text-muted">Alternative codes:</small>
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            @foreach($classification['alternatives'] as $alt)
                                            @php
                                                // Handle both string and array formats for alternatives
                                                $altCode = is_array($alt) ? ($alt['code'] ?? '') : $alt;
                                                $altDesc = is_array($alt) ? ($alt['description'] ?? '') : '';
                                                $altScore = is_array($alt) ? ($alt['score'] ?? null) : null;
                                            @endphp
                                            <label class="btn btn-sm btn-outline-secondary" title="{{ $altDesc }}{{ $altScore ? ' (' . $altScore . '%)' : '' }}">
                                                <input type="radio" 
                                                       class="btn-check code-selection" 
                                                       name="items[{{ $index }}][code_source]" 
                                                       value="alt_{{ $altCode }}"
                                                       data-code="{{ $altCode }}"
                                                       data-target="code_{{ $index }}">
                                                {{ $altCode }}
                                            </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    {{-- Manual Entry Option --}}
                                    <div class="form-check mb-2">
                                        <input type="radio" 
                                               class="form-check-input code-selection" 
                                               name="items[{{ $index }}][code_source]" 
                                               value="manual"
                                               data-code=""
                                               data-target="code_{{ $index }}"
                                               id="manual_{{ $index }}"
                                               {{ !$isSuccess || $isProhibited ? 'checked' : '' }}>
                                        <label class="form-check-label" for="manual_{{ $index }}">
                                            Enter code manually or search
                                        </label>
                                    </div>

                                    {{-- Classification Memory Search --}}
                                    <div class="mb-2">
                                        <button type="button" class="btn btn-sm btn-outline-info memory-search-btn" 
                                                data-description="{{ $item['description'] }}"
                                                data-target="{{ $index }}"
                                                data-country="{{ $country->id ?? '' }}">
                                            <i class="fas fa-brain me-1"></i>Find Similar (Memory)
                                        </button>
                                        <small class="text-muted ms-2">Search previous classifications</small>
                                    </div>
                                    <div class="memory-search-results mb-2" id="memory_results_{{ $index }}" style="display: none;"></div>

                                    {{-- Code Search/Input Field --}}
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" 
                                               class="form-control code-input" 
                                               id="code_{{ $index }}"
                                               name="items[{{ $index }}][customs_code]" 
                                               value="{{ $isSuccess && !$isProhibited ? $classification['code'] : '' }}"
                                               placeholder="Enter or search HS code..."
                                               required
                                               autocomplete="off"
                                               data-country="{{ $country->id ?? '' }}">
                                        <button type="button" class="btn btn-outline-primary search-code-btn" data-target="code_{{ $index }}">
                                            Search
                                        </button>
                                    </div>
                                    <div class="code-search-results mt-2" id="results_{{ $index }}" style="display: none;"></div>
                                    
                                    {{-- Hidden fields for item data --}}
                                    <input type="hidden" name="items[{{ $index }}][description]" value="{{ $item['description'] }}">
                                    <input type="hidden" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}">
                                    <input type="hidden" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] ?? 0 }}">
                                    <input type="hidden" name="items[{{ $index }}][sku]" value="{{ $item['sku'] ?? '' }}">
                                    <input type="hidden" name="items[{{ $index }}][item_number]" value="{{ $item['item_number'] ?? '' }}">
                                    <input type="hidden" name="items[{{ $index }}][line_number]" value="{{ $item['line_number'] ?? $index + 1 }}">
                                    {{-- Hidden fields for duty rate and description (populated by JS when code is selected) --}}
                                    <input type="hidden" 
                                           name="items[{{ $index }}][duty_rate]" 
                                           id="duty_rate_{{ $index }}"
                                           value="{{ $classification['duty_rate'] ?? '' }}">
                                    <input type="hidden" 
                                           name="items[{{ $index }}][customs_code_description]" 
                                           id="code_desc_{{ $index }}"
                                           value="{{ $classification['description'] ?? '' }}">
                                </div>

                                {{-- Override Indicator --}}
                                <div class="override-indicator text-warning small" id="override_{{ $index }}" style="display: none;">
                                    <i class="fas fa-edit me-1"></i>You are overriding the AI recommendation.
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Classification Path (Collapsible) --}}
                    @if(!empty($classification['classification_path']) && $isSuccess)
                    <div class="card-footer bg-light">
                        <details>
                            <summary class="small text-muted cursor-pointer">
                                <i class="fas fa-route me-1"></i>View classification path
                            </summary>
                            <ul class="small mt-2 mb-0 ps-3">
                                @foreach($classification['classification_path'] as $step)
                                <li class="text-muted">{{ $step }}</li>
                                @endforeach
                            </ul>
                        </details>
                    </div>
                    @endif
                </div>
            </div>
                    @endforeach
        </div>

        {{-- Submit Section --}}
        <div class="card bg-light border-0 sticky-bottom mt-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Ensure all items have an assigned HS code before finalizing.
                        </span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('invoices.review') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                        <button type="submit" class="btn btn-success btn-lg" id="finalizeBtn">
                            <i class="fas fa-check-circle me-2"></i>Finalize Invoice
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- Code Search Modal --}}
<div class="modal fade" id="codeSearchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-search me-2"></i>Search Customs Codes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="modalSearchInput" placeholder="Search by code or description...">
                    <button class="btn btn-primary" type="button" id="modalSearchBtn">Search</button>
                </div>
                <div id="modalSearchResults">
                    <p class="text-muted text-center py-4">Enter a search term to find customs codes.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.cursor-pointer { cursor: pointer; }
.sticky-bottom {
    position: sticky;
    bottom: 0;
    z-index: 100;
}
.code-search-results {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}
.code-search-results .search-result-item {
    padding: 0.5rem 0.75rem;
    cursor: pointer;
    border-bottom: 1px solid #eee;
}
.code-search-results .search-result-item:hover {
    background-color: #f8f9fa;
}
.code-search-results .search-result-item:last-child {
    border-bottom: none;
}
.memory-search-results {
    max-height: 250px;
    overflow-y: auto;
    border: 1px solid #17a2b8;
    border-radius: 0.375rem;
}
.memory-search-results .list-group-item:hover {
    background-color: #e8f4f8;
}
details summary {
    cursor: pointer;
}
details summary::-webkit-details-marker {
    display: none;
}
details summary::before {
    content: '▶ ';
    font-size: 0.7em;
}
details[open] summary::before {
    content: '▼ ';
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle code source selection (radio buttons)
    document.querySelectorAll('.code-selection').forEach(radio => {
        radio.addEventListener('change', function() {
            const targetId = this.dataset.target;
            const codeInput = document.getElementById(targetId);
            const overrideIndicator = document.getElementById('override_' + targetId.replace('code_', ''));
            const code = this.dataset.code;
            
            if (code) {
                codeInput.value = code;
            }
            
            // Show/hide override indicator
            const isAiSelected = this.value === 'ai';
            if (overrideIndicator) {
                overrideIndicator.style.display = isAiSelected ? 'none' : 'block';
            }
        });
    });

    // Handle manual code input changes
    document.querySelectorAll('.code-input').forEach(input => {
        input.addEventListener('input', function() {
            const index = this.id.replace('code_', '');
            const manualRadio = document.getElementById('manual_' + index);
            const overrideIndicator = document.getElementById('override_' + index);
            
            // Select manual radio when typing
            if (manualRadio) {
                manualRadio.checked = true;
            }
            
            // Show override indicator
            if (overrideIndicator) {
                overrideIndicator.style.display = 'block';
            }
        });
    });

    // Search button click handlers
    document.querySelectorAll('.search-code-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const input = document.getElementById(targetId);
            const query = input.value.trim();
            const countryId = input.dataset.country;
            
            if (query.length < 2) {
                alert('Please enter at least 2 characters to search.');
                return;
            }
            
            searchCodes(query, countryId, targetId);
        });
    });

    // Code search function
    function searchCodes(query, countryId, targetId) {
        const resultsDiv = document.getElementById('results_' + targetId.replace('code_', ''));
        resultsDiv.innerHTML = '<div class="p-3 text-center"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
        resultsDiv.style.display = 'block';
        
        fetch(`/api/customs-codes/search?q=${encodeURIComponent(query)}&country_id=${countryId}`)
            .then(response => response.json())
            .then(data => {
                if (data.codes && data.codes.length > 0) {
                    let html = '';
                    data.codes.forEach(code => {
                        html += `
                            <div class="search-result-item" 
                                 data-code="${code.code}" 
                                 data-target="${targetId}"
                                 data-duty-rate="${code.duty_rate || ''}"
                                 data-description="${(code.description || '').replace(/"/g, '&quot;')}">
                                <code class="fw-bold">${code.code}</code>
                                <small class="d-block text-muted">${code.description}</small>
                                ${code.duty_rate ? `<small class="badge bg-secondary">Duty: ${code.duty_rate}%</small>` : ''}
                            </div>
                        `;
                    });
                    resultsDiv.innerHTML = html;
                    
                    // Add click handlers to results
                    resultsDiv.querySelectorAll('.search-result-item').forEach(item => {
                        item.addEventListener('click', function() {
                            const code = this.dataset.code;
                            const dutyRate = this.dataset.dutyRate;
                            const description = this.dataset.description;
                            const targetInput = document.getElementById(this.dataset.target);
                            targetInput.value = code;
                            resultsDiv.style.display = 'none';
                            
                            // Update hidden fields for duty rate and description
                            const index = this.dataset.target.replace('code_', '');
                            const dutyRateInput = document.getElementById('duty_rate_' + index);
                            const descInput = document.getElementById('code_desc_' + index);
                            if (dutyRateInput) dutyRateInput.value = dutyRate;
                            if (descInput) descInput.value = description;
                            
                            // Select manual radio
                            const manualRadio = document.getElementById('manual_' + index);
                            if (manualRadio) manualRadio.checked = true;
                        });
                    });
                } else {
                    resultsDiv.innerHTML = '<div class="p-3 text-center text-muted">No codes found matching your search.</div>';
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                resultsDiv.innerHTML = '<div class="p-3 text-center text-danger">Search failed. Please try again.</div>';
            });
    }

    // Hide search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.code-search-results') && !e.target.closest('.search-code-btn') && !e.target.closest('.code-input')) {
            document.querySelectorAll('.code-search-results').forEach(div => {
                div.style.display = 'none';
            });
        }
        if (!e.target.closest('.memory-search-results') && !e.target.closest('.memory-search-btn')) {
            document.querySelectorAll('.memory-search-results').forEach(div => {
                div.style.display = 'none';
            });
        }
    });

    // Classification Memory Search
    document.querySelectorAll('.memory-search-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const description = this.dataset.description;
            const targetIndex = this.dataset.target;
            const countryId = this.dataset.country;
            
            searchClassificationMemory(description, countryId, targetIndex);
        });
    });

    function searchClassificationMemory(description, countryId, targetIndex) {
        const resultsDiv = document.getElementById('memory_results_' + targetIndex);
        resultsDiv.innerHTML = '<div class="p-2 text-center"><i class="fas fa-spinner fa-spin"></i> Searching classification memory...</div>';
        resultsDiv.style.display = 'block';
        
        // Extract keywords from description for search
        const searchQuery = description.substring(0, 50);
        
        fetch(`/api/classification-memory/search?q=${encodeURIComponent(searchQuery)}&country_id=${countryId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.results && data.results.length > 0) {
                    let html = '<div class="list-group list-group-flush">';
                    html += '<div class="list-group-item bg-info text-white py-1 small"><i class="fas fa-brain me-1"></i>Previously Classified Similar Items</div>';
                    
                    data.results.forEach(item => {
                        const timesUsed = item.times_used > 1 ? `<span class="badge bg-success ms-1">${item.times_used}x</span>` : '';
                        const dutyRate = item.duty_rate !== null ? `<span class="badge bg-secondary">${item.duty_rate}%</span>` : '';
                        
                        html += `
                            <div class="list-group-item list-group-item-action memory-result-item py-2" 
                                 data-code="${item.customs_code}"
                                 data-duty-rate="${item.duty_rate || ''}"
                                 data-description="${(item.customs_code_description || '').replace(/"/g, '&quot;')}"
                                 data-target="${targetIndex}"
                                 style="cursor: pointer;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <small class="text-muted d-block">${item.description}</small>
                                        <code class="fw-bold text-primary">${item.customs_code}</code>
                                        ${dutyRate}
                                        ${timesUsed}
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-success use-memory-btn">
                                        <i class="fas fa-check"></i> Use
                                    </button>
                                </div>
                                ${item.customs_code_description ? `<small class="text-muted">${item.customs_code_description}</small>` : ''}
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    resultsDiv.innerHTML = html;
                    
                    // Add click handlers
                    resultsDiv.querySelectorAll('.memory-result-item').forEach(item => {
                        item.addEventListener('click', function(e) {
                            if (e.target.closest('.use-memory-btn')) {
                                applyMemoryClassification(this);
                            }
                        });
                        
                        item.querySelector('.use-memory-btn').addEventListener('click', function(e) {
                            e.stopPropagation();
                            applyMemoryClassification(item);
                        });
                    });
                } else {
                    resultsDiv.innerHTML = '<div class="alert alert-info mb-0 py-2 small"><i class="fas fa-info-circle me-1"></i>No similar items found in classification memory.</div>';
                }
            })
            .catch(error => {
                console.error('Memory search error:', error);
                resultsDiv.innerHTML = '<div class="alert alert-danger mb-0 py-2 small">Search failed. Please try again.</div>';
            });
    }

    function applyMemoryClassification(element) {
        const code = element.dataset.code;
        const dutyRate = element.dataset.dutyRate;
        const description = element.dataset.description;
        const targetIndex = element.dataset.target;
        
        // Update the code input
        const codeInput = document.getElementById('code_' + targetIndex);
        if (codeInput) codeInput.value = code;
        
        // Update hidden fields
        const dutyRateInput = document.getElementById('duty_rate_' + targetIndex);
        const descInput = document.getElementById('code_desc_' + targetIndex);
        if (dutyRateInput) dutyRateInput.value = dutyRate;
        if (descInput) descInput.value = description;
        
        // Select manual radio
        const manualRadio = document.getElementById('manual_' + targetIndex);
        if (manualRadio) manualRadio.checked = true;
        
        // Show override indicator
        const overrideIndicator = document.getElementById('override_' + targetIndex);
        if (overrideIndicator) overrideIndicator.style.display = 'block';
        
        // Hide the memory results
        const resultsDiv = document.getElementById('memory_results_' + targetIndex);
        if (resultsDiv) resultsDiv.style.display = 'none';
        
        // Visual feedback
        codeInput.classList.add('is-valid');
        setTimeout(() => codeInput.classList.remove('is-valid'), 2000);
    }

    // Form validation
    document.getElementById('assignCodesForm').addEventListener('submit', function(e) {
        const codeInputs = this.querySelectorAll('.code-input');
        let allFilled = true;
        
        codeInputs.forEach(input => {
            if (!input.value.trim()) {
                allFilled = false;
                input.classList.add('is-invalid');
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        if (!allFilled) {
            e.preventDefault();
            alert('Please assign an HS code to all items before finalizing.');
        }
    });
});
</script>
@endpush
