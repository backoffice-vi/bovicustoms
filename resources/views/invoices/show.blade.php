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
                            <th style="width: 150px">HS Code</th>
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
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="5" class="text-end fw-bold">Invoice Total:</td>
                            <td class="text-end fw-bold fs-5">${{ number_format($invoice->total_amount, 2) }}</td>
                            <td></td>
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
    .btn, nav, .alert, .hs-edit {
        display: none !important;
    }
    .card {
        border: 1px solid #ddd !important;
    }
}
.hs-code-cell { position: relative; min-width: 160px; }
.hs-display:hover { background: #f0f4ff; border-radius: 4px; padding: 2px 4px; margin: -2px -4px; }
.hs-results .list-group-item { cursor: pointer; padding: 6px 10px; font-size: 0.8rem; }
.hs-results .list-group-item:hover { background: #e8f0fe; }
.hs-results .list-group-item code { font-weight: 700; }
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
});
</script>
@endpush
