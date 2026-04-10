@extends('layouts.app')

@section('title', 'Invoice Details - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-file-invoice me-2"></i>Invoice Details</h2>
            <p class="text-muted mb-0">{{ $invoice->invoice_number }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Invoices
            </a>
            <button class="btn btn-outline-primary" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print
            </button>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Classification Alert for stuck/failed invoices --}}
    @php
        $unclassifiedItems = $items->filter(fn($item) => empty($item->customs_code));
        $classifiedItems = $items->filter(fn($item) => !empty($item->customs_code));
        $hasUnclassifiedItems = $unclassifiedItems->isNotEmpty();
        $isStuck = $invoice->status === 'classifying' || ($invoice->status !== 'processed' && $hasUnclassifiedItems);
    @endphp
    
    @if($isStuck)
    <div class="alert alert-warning" role="alert">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>{{ $classifiedItems->count() }}/{{ $items->count() }} items classified.</strong>
                @if($invoice->status === 'classifying')
                    Classification is still running or may have failed.
                @else
                    {{ $unclassifiedItems->count() }} {{ Str::plural('item', $unclassifiedItems->count()) }} still need HS codes — you can assign them manually below or retry.
                @endif
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <form action="{{ route('invoices.retry_classification', $invoice) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-warning">
                    <i class="fas fa-redo me-1"></i>Retry Failed ({{ $unclassifiedItems->count() }})
                </button>
            </form>
            <form action="{{ route('invoices.retry_classification', $invoice) }}" method="POST" class="d-inline">
                @csrf
                <input type="hidden" name="retry_all" value="1">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="fas fa-sync me-1"></i>Reclassify All
                </button>
            </form>
            @if($invoice->status !== 'classifying' && $classifiedItems->count() > 0)
                <form action="{{ route('invoices.accept_classification', $invoice) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="fas fa-check me-1"></i>Proceed as Is
                    </button>
                </form>
            @endif
        </div>
    </div>
    @endif

    @if(in_array($invoice->status, ['classified', 'partially_classified']) && $classifiedItems->count() > 0)
    <div class="alert alert-info" role="alert">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <i class="fas fa-clipboard-check me-2"></i>
                <strong>Ready for review.</strong>
                Compare BoVi AI recommendations against historical classifications before finalizing.
            </div>
            <a href="{{ route('invoices.assign_codes_results', $invoice) }}" class="btn btn-primary btn-sm">
                <i class="fas fa-balance-scale me-2"></i>Review &amp; Confirm Codes
            </a>
        </div>
    </div>
    @endif

    {{-- Invoice Header --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small d-block">Invoice Number</label>
                    <strong class="fs-5">{{ $invoice->invoice_number }}</strong>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small d-block">Invoice Date</label>
                    <strong>{{ $invoice->invoice_date?->format('F d, Y') ?? 'N/A' }}</strong>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small d-block">Country</label>
                    <strong>
                        @if($invoice->country)
                            <i class="fas fa-flag me-1"></i>{{ $invoice->country->name }}
                        @else
                            N/A
                        @endif
                    </strong>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small d-block">Status</label>
                    @php
                        $statusClass = match($invoice->status) {
                            'processed' => 'success',
                            'classified' => 'success',
                            'partially_classified' => 'warning',
                            'pending' => 'warning',
                            'classifying' => 'info',
                            'draft' => 'secondary',
                            default => 'primary'
                        };
                        $statusLabel = match($invoice->status) {
                            'partially_classified' => 'Partially Classified',
                            default => ucfirst($invoice->status),
                        };
                    @endphp
                    <span class="badge bg-{{ $statusClass }} fs-6">{{ $statusLabel }}</span>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-3">
                    <label class="text-muted small d-block">Total Amount</label>
                    <strong class="fs-4 text-primary">${{ number_format($invoice->total_amount, 2) }}</strong>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small d-block">Line Items</label>
                    <strong>{{ $items->count() }} items</strong>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small d-block">Created</label>
                    <strong>{{ $invoice->created_at->format('M d, Y H:i') }}</strong>
                </div>
                <div class="col-md-3">
                    <label class="text-muted small d-block">Source</label>
                    <strong>{{ ucfirst($invoice->source_type ?? 'Manual') }}</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Line Items --}}
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>Line Items</h5>
            <span class="badge bg-primary">{{ $items->count() }} items</span>
        </div>
        <div class="card-body p-0">
            @if($items->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px">#</th>
                            <th style="width: 100px">SKU</th>
                            <th>Description</th>
                            <th style="width: 80px" class="text-center">Qty</th>
                            <th style="width: 120px" class="text-end">Unit Price</th>
                            <th style="width: 120px" class="text-end">Total</th>
                            <th style="width: 200px">HS Code</th>
                            <th style="width: 60px" class="text-center">Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                        <tr>
                            <td class="text-muted">{{ $item->line_number }}</td>
                            <td>
                                @if($item->sku)
                                    <code class="small">{{ $item->sku }}</code>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                {{ $item->description }}
                                @if($item->item_number)
                                    <br><small class="text-muted">Item #: {{ $item->item_number }}</small>
                                @endif
                            </td>
                            <td class="text-center">{{ number_format($item->quantity ?? 1, $item->quantity == intval($item->quantity) ? 0 : 2) }}</td>
                            <td class="text-end">
                                @if($item->unit_price)
                                    ${{ number_format($item->unit_price, 2) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-end fw-semibold">
                                @if($item->line_total)
                                    ${{ number_format($item->line_total, 2) }}
                                @elseif($item->quantity && $item->unit_price)
                                    ${{ number_format($item->quantity * $item->unit_price, 2) }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="hs-code-cell" data-item-id="{{ $item->id }}">
                                <div class="hs-display" style="cursor: pointer;" title="Click to edit">
                                    @if($item->customs_code)
                                        <code class="fw-bold text-primary">{{ $item->customs_code }}</code>
                                        <i class="fas fa-pencil-alt text-muted ms-1" style="font-size: 0.65rem;"></i>
                                        @if($item->customs_code_description)
                                            <div class="text-muted small mt-1" style="font-size: 0.72rem; line-height: 1.3;">
                                                {{ Str::limit($item->customs_code_description, 80) }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-danger"><i class="fas fa-plus-circle me-1"></i>Assign</span>
                                    @endif
                                </div>
                                <div class="hs-edit" style="display: none;">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm hs-search"
                                            placeholder="Search HS code..."
                                            value="{{ $item->customs_code }}"
                                            data-item-id="{{ $item->id }}">
                                        <button class="btn btn-sm btn-success hs-save" type="button" title="Save">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary hs-cancel" type="button" title="Cancel">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="hs-results list-group mt-1" style="display: none; position: absolute; z-index: 100; max-height: 200px; overflow-y: auto; width: 280px;"></div>
                                </div>
                            </td>
                            <td class="text-center">
                                @if($item->customs_code)
                                <button class="btn btn-sm btn-outline-info review-classification-btn"
                                        data-item-id="{{ $item->id }}"
                                        data-item-desc="{{ $item->description }}"
                                        title="Review classification details">
                                    <i class="fas fa-search-plus"></i>
                                </button>
                                @else
                                <span class="text-muted" style="font-size: 0.7rem;">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="5" class="text-end fw-bold">Invoice Total:</td>
                            <td class="text-end fw-bold fs-5">${{ number_format($invoice->total_amount, 2) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @else
            {{-- Fallback to JSON items if no InvoiceItem records --}}
            @php
                $jsonItems = is_array($invoice->items) ? $invoice->items : json_decode($invoice->items ?? '[]', true);
            @endphp
            @if(!empty($jsonItems))
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Description</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Total</th>
                            <th>HS Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($jsonItems as $index => $item)
                        <tr>
                            <td class="text-muted">{{ $index + 1 }}</td>
                            <td>{{ $item['description'] ?? 'N/A' }}</td>
                            <td class="text-center">{{ $item['quantity'] ?? 1 }}</td>
                            <td class="text-end">${{ number_format($item['unit_price'] ?? 0, 2) }}</td>
                            <td class="text-end fw-semibold">${{ number_format(($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0), 2) }}</td>
                            <td>
                                @if(isset($item['customs_code']))
                                    <code class="fw-bold text-primary">{{ $item['customs_code'] }}</code>
                                @else
                                    <span class="text-muted">Not assigned</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">No line items found for this invoice.</p>
            </div>
            @endif
            @endif
        </div>
    </div>

    {{-- Extraction Info (if available) --}}
    @if($invoice->extraction_meta)
    <div class="card mt-4">
        <div class="card-header bg-white">
            <h6 class="mb-0"><i class="fas fa-info-circle me-2 text-info"></i>Extraction Information</h6>
        </div>
        <div class="card-body">
            <div class="row small">
                @php $meta = is_array($invoice->extraction_meta) ? $invoice->extraction_meta : json_decode($invoice->extraction_meta, true); @endphp
                @if(isset($meta['mode']))
                <div class="col-md-3">
                    <span class="text-muted">Extraction Mode:</span>
                    <strong class="d-block">{{ ucfirst(str_replace('_', ' ', $meta['mode'])) }}</strong>
                </div>
                @endif
                @if(isset($meta['file_ext']))
                <div class="col-md-3">
                    <span class="text-muted">File Type:</span>
                    <strong class="d-block">.{{ strtoupper($meta['file_ext']) }}</strong>
                </div>
                @endif
                @if(isset($meta['size_bytes']))
                <div class="col-md-3">
                    <span class="text-muted">File Size:</span>
                    <strong class="d-block">{{ number_format($meta['size_bytes'] / 1024, 1) }} KB</strong>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Classification Review Modal --}}
    <div class="modal fade" id="classificationReviewModal" tabindex="-1" aria-labelledby="classificationReviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="classificationReviewModalLabel">
                        <i class="fas fa-search-plus me-2 text-info"></i>Classification Review
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="reviewModalBody">
                    <div class="text-center py-5" id="reviewModalLoading">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="text-muted mt-2">Loading classification data...</p>
                    </div>
                    <div id="reviewModalContent" style="display: none;">
                        {{-- Item summary --}}
                        <div class="border rounded p-3 mb-4 bg-light">
                            <h6 class="mb-2" id="reviewItemDesc"></h6>
                            <div class="d-flex gap-3 small text-muted">
                                <span id="reviewItemSku"></span>
                            </div>
                        </div>

                        {{-- Current Assigned Code --}}
                        <div class="mb-4" id="reviewCurrentSection">
                            <h6 class="fw-semibold border-bottom pb-2 mb-3">
                                <i class="fas fa-barcode me-2 text-primary"></i>Currently Assigned Code
                            </h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted d-block">HS Code</small>
                                        <code class="fs-4 fw-bold text-primary" id="reviewCurrentCode"></code>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="border rounded p-3">
                                        <small class="text-muted d-block">Official Description</small>
                                        <span id="reviewCurrentDesc" class="small"></span>
                                        <div class="mt-2 d-flex gap-3" id="reviewCurrentMeta">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- AI Classification --}}
                        <div class="mb-4" id="reviewAiSection" style="display: none;">
                            <h6 class="fw-semibold border-bottom pb-2 mb-3">
                                <i class="fas fa-robot me-2 text-success"></i>AI Classification
                            </h6>
                            <div id="reviewAiContent"></div>
                        </div>

                        {{-- Historical Precedents --}}
                        <div class="mb-4" id="reviewPrecedentsSection" style="display: none;">
                            <h6 class="fw-semibold border-bottom pb-2 mb-3">
                                <i class="fas fa-history me-2 text-info"></i>Historical Precedents
                            </h6>
                            <div class="list-group" id="reviewPrecedentsList"></div>
                        </div>

                        {{-- Classification Memory --}}
                        <div class="mb-4" id="reviewMemorySection" style="display: none;">
                            <h6 class="fw-semibold border-bottom pb-2 mb-3">
                                <i class="fas fa-brain me-2 text-purple"></i>Previously Classified (Same Item)
                            </h6>
                            <div class="list-group" id="reviewMemoryList"></div>
                        </div>

                        {{-- No extra data --}}
                        <div id="reviewNoExtraData" style="display: none;">
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-info-circle me-2"></i>No AI classification or historical data available for this item.
                            </div>
                        </div>

                        {{-- Change Code Section --}}
                        <div class="mb-3 border-top pt-3" id="reviewChangeSection">
                            <h6 class="fw-semibold border-bottom pb-2 mb-3">
                                <i class="fas fa-exchange-alt me-2 text-warning"></i>Change HS Code
                            </h6>
                            <div id="reviewSelectedCode" class="alert alert-info py-2 small" style="display: none;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="fas fa-arrow-right me-2"></i>
                                        New code: <code class="fw-bold fs-6" id="reviewSelectedCodeValue"></code>
                                        <span class="text-muted ms-2" id="reviewSelectedCodeDesc"></span>
                                    </span>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0" id="reviewClearSelection" title="Clear selection">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="reviewCodeSearch" placeholder="Search HS codes by number or description...">
                            </div>
                            <div class="list-group mb-2" id="reviewSearchResults" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                            <small class="text-muted">Search above, or click <strong>"Use This"</strong> next to any code shown in the sections above.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="reviewSaveBtn" style="display: none;">
                        <i class="fas fa-save me-1"></i>Save New Code
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Action Buttons --}}
    <div class="d-flex justify-content-between mt-4">
        <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Invoices
        </a>
        <div class="d-flex gap-2">
            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Dashboard
            </a>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
@media print {
    .btn, nav, .alert, .hs-edit, .review-classification-btn {
        display: none !important;
    }
    .card {
        border: 1px solid #ddd !important;
    }
}
.hs-code-cell { position: relative; min-width: 180px; }
.hs-display:hover { background: #f0f4ff; border-radius: 4px; padding: 2px 4px; margin: -2px -4px; }
.hs-results .list-group-item { cursor: pointer; padding: 6px 10px; font-size: 0.8rem; }
.hs-results .list-group-item:hover { background: #e8f0fe; }
.hs-results .list-group-item code { font-weight: 700; }
.review-classification-btn { padding: 0.15rem 0.4rem; font-size: 0.75rem; }
.text-purple { color: #6f42c1; }
#reviewModalContent .confidence-bar { height: 22px; border-radius: 4px; }
#reviewModalContent .ai-card { border-left: 3px solid #198754; }
#reviewModalContent .precedent-item { transition: background 0.15s; }
#reviewModalContent .precedent-item:hover { background: #f0f4ff; }
#reviewModalContent .code-match { background: #e8f5e9; border-radius: 3px; padding: 0 4px; }
#reviewModalContent .code-differ { background: #fff3e0; border-radius: 3px; padding: 0 4px; }
.use-code-btn { font-size: 0.7rem; padding: 0.1rem 0.45rem; white-space: nowrap; }
#reviewSearchResults .list-group-item { cursor: pointer; padding: 6px 10px; font-size: 0.85rem; }
#reviewSearchResults .list-group-item:hover { background: #e8f0fe; }
#reviewSelectedCode { transition: all 0.2s; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '{{ csrf_token() }}';
    let searchTimeout = null;

    document.querySelectorAll('.hs-code-cell').forEach(cell => {
        const display = cell.querySelector('.hs-display');
        const edit = cell.querySelector('.hs-edit');
        const input = cell.querySelector('.hs-search');
        const results = cell.querySelector('.hs-results');
        const saveBtn = cell.querySelector('.hs-save');
        const cancelBtn = cell.querySelector('.hs-cancel');
        const itemId = cell.dataset.itemId;

        display.addEventListener('click', () => {
            display.style.display = 'none';
            edit.style.display = 'block';
            input.focus();
            input.select();
        });

        cancelBtn.addEventListener('click', () => {
            edit.style.display = 'none';
            display.style.display = 'block';
            results.style.display = 'none';
        });

        saveBtn.addEventListener('click', () => saveCode(itemId, input.value.trim(), cell));

        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveCode(itemId, input.value.trim(), cell);
            }
            if (e.key === 'Escape') {
                cancelBtn.click();
            }
        });

        input.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            const q = input.value.trim();
            if (q.length < 2) { results.style.display = 'none'; return; }
            searchTimeout = setTimeout(() => searchCodes(q, results, input, itemId, cell), 300);
        });
    });

    function searchCodes(query, resultsEl, input, itemId, cell) {
        fetch('/api/customs-codes/search?q=' + encodeURIComponent(query) + '&country_id={{ $invoice->country_id }}', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                resultsEl.innerHTML = '<div class="list-group-item text-muted small">No codes found</div>';
                resultsEl.style.display = 'block';
                return;
            }
            resultsEl.innerHTML = data.slice(0, 8).map(code =>
                `<div class="list-group-item" data-code="${code.code}" data-desc="${code.description || ''}">
                    <code>${code.code}</code> <span class="text-muted small">${(code.description || '').substring(0, 60)}</span>
                </div>`
            ).join('');
            resultsEl.style.display = 'block';

            resultsEl.querySelectorAll('.list-group-item[data-code]').forEach(item => {
                item.addEventListener('click', () => {
                    input.value = item.dataset.code;
                    resultsEl.style.display = 'none';
                    saveCode(itemId, item.dataset.code, cell, item.dataset.desc);
                });
            });
        })
        .catch(() => { resultsEl.style.display = 'none'; });
    }

    function saveCode(itemId, code, cell, description) {
        if (!code) return;
        fetch('/invoices/items/' + itemId + '/update-code', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ customs_code: code, customs_code_description: description || null })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const display = cell.querySelector('.hs-display');
                const edit = cell.querySelector('.hs-edit');
                display.innerHTML = `<code class="fw-bold text-primary">${data.customs_code}</code> <i class="fas fa-pencil-alt text-muted ms-1" style="font-size: 0.65rem;"></i>`;
                edit.style.display = 'none';
                display.style.display = 'block';
                cell.querySelector('.hs-results').style.display = 'none';
            }
        })
        .catch(err => alert('Failed to save: ' + err.message));
    }

    // Classification Review Modal
    const reviewModal = new bootstrap.Modal(document.getElementById('classificationReviewModal'));
    let currentReviewItemId = null;
    let selectedNewCode = null;
    let selectedNewDesc = null;
    let modalSearchTimeout = null;

    document.querySelectorAll('.review-classification-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = this.dataset.itemId;
            openClassificationReview(itemId);
        });
    });

    // Modal search
    document.getElementById('reviewCodeSearch').addEventListener('input', function() {
        clearTimeout(modalSearchTimeout);
        const q = this.value.trim();
        const resultsEl = document.getElementById('reviewSearchResults');
        if (q.length < 2) { resultsEl.style.display = 'none'; return; }
        modalSearchTimeout = setTimeout(() => {
            fetch('/api/customs-codes/search?q=' + encodeURIComponent(q) + '&country_id={{ $invoice->country_id }}', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            })
            .then(r => r.json())
            .then(data => {
                const codes = data.codes || data;
                if (!codes || !codes.length) {
                    resultsEl.innerHTML = '<div class="list-group-item text-muted small">No codes found</div>';
                    resultsEl.style.display = 'block';
                    return;
                }
                resultsEl.innerHTML = codes.slice(0, 10).map(c =>
                    `<div class="list-group-item d-flex justify-content-between align-items-center" data-code="${escAttr(c.code)}" data-desc="${escAttr(c.description || '')}">
                        <div>
                            <code class="fw-bold">${escHtml(c.code)}</code>
                            <span class="text-muted small ms-1">${escHtml((c.description || '').substring(0, 70))}</span>
                        </div>
                        <button class="btn btn-sm btn-outline-primary use-code-btn" type="button">Use This</button>
                    </div>`
                ).join('');
                resultsEl.style.display = 'block';

                resultsEl.querySelectorAll('.use-code-btn').forEach(b => {
                    b.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const row = this.closest('.list-group-item');
                        selectNewCode(row.dataset.code, row.dataset.desc);
                    });
                });
                resultsEl.querySelectorAll('.list-group-item[data-code]').forEach(row => {
                    row.addEventListener('click', function() {
                        selectNewCode(this.dataset.code, this.dataset.desc);
                    });
                });
            })
            .catch(() => { resultsEl.style.display = 'none'; });
        }, 300);
    });

    // Clear selection
    document.getElementById('reviewClearSelection').addEventListener('click', function() {
        clearCodeSelection();
    });

    // Save button
    document.getElementById('reviewSaveBtn').addEventListener('click', function() {
        if (!currentReviewItemId || !selectedNewCode) return;
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

        fetch('/invoices/items/' + currentReviewItemId + '/update-code', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ customs_code: selectedNewCode, customs_code_description: selectedNewDesc || null })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Update the table row
                const cell = document.querySelector(`.hs-code-cell[data-item-id="${currentReviewItemId}"]`);
                if (cell) {
                    const display = cell.querySelector('.hs-display');
                    display.innerHTML = `<code class="fw-bold text-primary">${escHtml(data.customs_code)}</code> <i class="fas fa-pencil-alt text-muted ms-1" style="font-size: 0.65rem;"></i>`
                        + (selectedNewDesc ? `<div class="text-muted small mt-1" style="font-size: 0.72rem; line-height: 1.3;">${escHtml(selectedNewDesc.substring(0, 80))}</div>` : '');
                    const input = cell.querySelector('.hs-search');
                    if (input) input.value = data.customs_code;
                }

                // Update the "Currently Assigned" section in the modal
                document.getElementById('reviewCurrentCode').textContent = data.customs_code;
                if (selectedNewDesc) {
                    document.getElementById('reviewCurrentDesc').textContent = selectedNewDesc;
                }

                clearCodeSelection();

                // Show success flash
                const flash = document.createElement('div');
                flash.className = 'alert alert-success alert-dismissible fade show py-2 small mt-2';
                flash.innerHTML = `<i class="fas fa-check-circle me-1"></i>Code updated to <strong>${escHtml(data.customs_code)}</strong>
                    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" style="padding:0.4rem;"></button>`;
                document.getElementById('reviewChangeSection').prepend(flash);
                setTimeout(() => flash.remove(), 4000);
            }
        })
        .catch(err => alert('Failed to save: ' + err.message))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-1"></i>Save New Code';
        });
    });

    function selectNewCode(code, desc) {
        selectedNewCode = code;
        selectedNewDesc = desc || '';
        document.getElementById('reviewSelectedCodeValue').textContent = code;
        document.getElementById('reviewSelectedCodeDesc').textContent = desc ? desc.substring(0, 80) : '';
        document.getElementById('reviewSelectedCode').style.display = 'block';
        document.getElementById('reviewSaveBtn').style.display = 'inline-block';
        document.getElementById('reviewSearchResults').style.display = 'none';
        document.getElementById('reviewCodeSearch').value = '';
    }

    function clearCodeSelection() {
        selectedNewCode = null;
        selectedNewDesc = null;
        document.getElementById('reviewSelectedCode').style.display = 'none';
        document.getElementById('reviewSaveBtn').style.display = 'none';
    }

    function openClassificationReview(itemId) {
        currentReviewItemId = itemId;
        clearCodeSelection();
        document.getElementById('reviewCodeSearch').value = '';
        document.getElementById('reviewSearchResults').style.display = 'none';
        document.getElementById('reviewModalLoading').style.display = 'block';
        document.getElementById('reviewModalContent').style.display = 'none';
        reviewModal.show();

        fetch('/invoices/items/' + itemId + '/classification-review', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Failed to load');
            renderReviewModal(data);
        })
        .catch(err => {
            document.getElementById('reviewModalLoading').innerHTML =
                `<div class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>${err.message}</div>`;
        });
    }

    function renderReviewModal(data) {
        const item = data.item;
        const codeRecord = data.customs_code_record;
        const ai = data.ai_classification;
        const precedents = data.precedents || [];
        const memory = data.memory_matches || [];
        let hasExtraData = false;

        // Item summary
        document.getElementById('reviewItemDesc').textContent = item.description;
        document.getElementById('reviewItemSku').innerHTML = item.sku
            ? `<span>SKU: <code>${escHtml(item.sku)}</code></span>` : '';

        // Current assigned code
        if (item.customs_code) {
            document.getElementById('reviewCurrentSection').style.display = 'block';
            document.getElementById('reviewCurrentCode').textContent = item.customs_code;
            const desc = codeRecord ? codeRecord.description : (item.customs_code_description || 'No description available');
            document.getElementById('reviewCurrentDesc').textContent = desc;

            let metaHtml = '';
            if (codeRecord) {
                metaHtml += `<span class="badge bg-secondary"><i class="fas fa-percentage me-1"></i>Duty: ${codeRecord.formatted_duty}</span>`;
                if (codeRecord.unit_of_measurement) {
                    metaHtml += `<span class="badge bg-outline-secondary border"><i class="fas fa-ruler me-1"></i>${codeRecord.unit_of_measurement}</span>`;
                }
                if (codeRecord.chapter_number) {
                    metaHtml += `<span class="badge bg-outline-secondary border">Chapter ${codeRecord.chapter_number}</span>`;
                }
            } else if (item.duty_rate) {
                metaHtml += `<span class="badge bg-secondary">Duty: ${item.duty_rate}%</span>`;
            }
            document.getElementById('reviewCurrentMeta').innerHTML = metaHtml;
        } else {
            document.getElementById('reviewCurrentSection').style.display = 'none';
        }

        // AI Classification
        if (ai && ai.success) {
            hasExtraData = true;
            document.getElementById('reviewAiSection').style.display = 'block';
            let aiHtml = '<div class="card ai-card mb-3"><div class="card-body">';

            const aiMatchesCurrent = ai.code === item.customs_code;
            aiHtml += `<div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <span class="text-muted small">AI Recommended Code</span><br>
                    <code class="fs-5 fw-bold ${aiMatchesCurrent ? 'text-success' : 'text-warning'}">${escHtml(ai.code)}</code>
                    ${aiMatchesCurrent
                        ? '<span class="badge bg-success ms-2"><i class="fas fa-check me-1"></i>Matches current</span>'
                        : '<span class="badge bg-warning text-dark ms-2"><i class="fas fa-exclamation me-1"></i>Differs from current</span>'}
                </div>
                <div class="d-flex align-items-center gap-2">`;

            if (ai.confidence !== undefined) {
                const confClass = ai.confidence >= 80 ? 'success' : (ai.confidence >= 60 ? 'warning' : 'danger');
                aiHtml += `<span class="badge bg-${confClass} fs-6">${ai.confidence}%</span>`;
            }
            if (!aiMatchesCurrent) {
                aiHtml += `<button class="btn btn-sm btn-outline-primary use-code-btn" onclick="window._selectCode('${escAttr(ai.code)}','${escAttr(ai.description || '')}')">Use This</button>`;
            }
            aiHtml += '</div></div>';

            if (ai.description) {
                aiHtml += `<div class="small text-muted mb-2">${escHtml(ai.description)}</div>`;
            }

            if (ai.confidence !== undefined) {
                const confClass = ai.confidence >= 80 ? 'bg-success' : (ai.confidence >= 60 ? 'bg-warning' : 'bg-danger');
                aiHtml += `<div class="progress confidence-bar mb-2">
                    <div class="progress-bar ${confClass}" role="progressbar" style="width: ${ai.confidence}%">${ai.confidence}% confidence</div>
                </div>`;
            }

            if (ai.explanation) {
                aiHtml += `<div class="bg-light p-2 rounded small mt-2"><i class="fas fa-lightbulb text-warning me-2"></i>${escHtml(ai.explanation)}</div>`;
            }

            if (ai.duty_rate !== undefined) {
                aiHtml += `<div class="mt-2 small"><i class="fas fa-percentage me-1 text-muted"></i>Duty Rate: <strong>${ai.duty_rate}%</strong></div>`;
            }

            aiHtml += '</div></div>';

            // Alternatives
            if (ai.alternatives && ai.alternatives.length > 0) {
                aiHtml += '<div class="mb-2"><small class="fw-semibold text-muted">Alternative Codes Considered:</small></div>';
                aiHtml += '<div class="d-flex flex-wrap gap-2">';
                ai.alternatives.forEach(alt => {
                    const altCode = typeof alt === 'string' ? alt : (alt.code || '');
                    const altDesc = typeof alt === 'object' ? (alt.description || '') : '';
                    const altScore = typeof alt === 'object' ? (alt.score || '') : '';
                    aiHtml += `<span class="badge bg-light text-dark border" style="cursor:pointer;" title="${escHtml(altDesc)}" onclick="window._selectCode('${escAttr(altCode)}','${escAttr(altDesc)}')">
                        <code>${escHtml(altCode)}</code>
                        ${altScore ? `<small class="text-muted ms-1">${altScore}%</small>` : ''}
                        <i class="fas fa-arrow-right ms-1 text-primary" style="font-size:0.6rem;"></i>
                    </span>`;
                });
                aiHtml += '</div>';
            }

            document.getElementById('reviewAiContent').innerHTML = aiHtml;
        } else {
            document.getElementById('reviewAiSection').style.display = 'none';
        }

        // Historical Precedents
        if (precedents.length > 0) {
            hasExtraData = true;
            document.getElementById('reviewPrecedentsSection').style.display = 'block';
            let precHtml = '';
            precedents.forEach(p => {
                const isMatch = p.hs_code === item.customs_code;
                precHtml += `<div class="list-group-item precedent-item d-flex justify-content-between align-items-start">
                    <div>
                        <code class="fw-bold ${isMatch ? 'code-match' : 'code-differ'}">${escHtml(p.hs_code)}</code>
                        ${isMatch ? '<i class="fas fa-check-circle text-success ms-1" title="Matches current code"></i>' : ''}
                        <div class="small text-muted mt-1">${escHtml(p.description.substring(0, 100))}</div>
                        ${p.hs_description ? `<div class="small text-muted fst-italic">${escHtml(p.hs_description.substring(0, 80))}</div>` : ''}
                    </div>
                    <div class="text-end d-flex flex-column align-items-end gap-1">
                        <small class="text-muted">${escHtml(p.created_at)}</small>
                        ${!isMatch ? `<button class="btn btn-sm btn-outline-primary use-code-btn" onclick="window._selectCode('${escAttr(p.hs_code)}','${escAttr(p.hs_description || '')}')">Use This</button>` : ''}
                    </div>
                </div>`;
            });
            document.getElementById('reviewPrecedentsList').innerHTML = precHtml;
        } else {
            document.getElementById('reviewPrecedentsSection').style.display = 'none';
        }

        // Classification Memory
        if (memory.length > 0) {
            hasExtraData = true;
            document.getElementById('reviewMemorySection').style.display = 'block';
            let memHtml = '';
            memory.forEach(m => {
                const isMatch = m.customs_code === item.customs_code;
                memHtml += `<div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <code class="fw-bold ${isMatch ? 'code-match' : 'code-differ'}">${escHtml(m.customs_code)}</code>
                        ${isMatch ? '<i class="fas fa-check-circle text-success ms-1"></i>' : ''}
                        <span class="small text-muted ms-2">${escHtml(m.customs_code_description || '')}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <small class="text-muted">${escHtml(m.date)}</small>
                        ${!isMatch ? `<button class="btn btn-sm btn-outline-primary use-code-btn" onclick="window._selectCode('${escAttr(m.customs_code)}','${escAttr(m.customs_code_description || '')}')">Use This</button>` : ''}
                    </div>
                </div>`;
            });
            document.getElementById('reviewMemoryList').innerHTML = memHtml;
        } else {
            document.getElementById('reviewMemorySection').style.display = 'none';
        }

        document.getElementById('reviewNoExtraData').style.display =
            hasExtraData ? 'none' : 'block';

        document.getElementById('reviewModalLoading').style.display = 'none';
        document.getElementById('reviewModalContent').style.display = 'block';
    }

    // Global helper so inline onclick handlers work
    window._selectCode = function(code, desc) {
        selectNewCode(code, desc);
        document.getElementById('reviewChangeSection').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escAttr(str) {
        if (!str) return '';
        return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }
});
</script>
@endpush
