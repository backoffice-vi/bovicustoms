@extends('layouts.app')

@section('title', 'Review Extracted Invoice - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-file-invoice me-2"></i>Review Extracted Invoice</h2>
            <p class="text-muted mb-0">Verify the extracted data before classification</p>
        </div>
        <a href="{{ route('invoices.create') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Upload Different File
        </a>
    </div>

    {{-- Extraction Metadata --}}
    @if(isset($extractedData['extraction_meta']))
    <div class="card mb-4 border-0 bg-light">
        <div class="card-body py-3">
            <div class="row align-items-center">
                <div class="col-auto">
                    <span class="badge bg-success fs-6">
                        <i class="fas fa-check-circle me-1"></i>Extraction Complete
                    </span>
                </div>
                <div class="col">
                    <small class="text-muted">
                        @php
                            $meta = $extractedData['extraction_meta'];
                            $modeLabels = [
                                'vision' => 'AI Vision (Image)',
                                'text' => 'Text Extraction',
                                'unstructured_api' => 'Advanced OCR',
                                'unstructured_ocr' => 'OCR Processing',
                            ];
                            $mode = $modeLabels[$meta['mode'] ?? ''] ?? ucfirst($meta['mode'] ?? 'Unknown');
                        @endphp
                        <i class="fas fa-cog me-1"></i>Method: <strong>{{ $mode }}</strong>
                        @if(isset($meta['file_ext']))
                            &nbsp;|&nbsp;<i class="fas fa-file me-1"></i>File: <strong>.{{ strtoupper($meta['file_ext']) }}</strong>
                        @endif
                        @if(isset($meta['size_bytes']))
                            &nbsp;|&nbsp;<i class="fas fa-weight me-1"></i>Size: <strong>{{ number_format($meta['size_bytes'] / 1024, 1) }} KB</strong>
                        @endif
                    </small>
                </div>
                @if(isset($country))
                <div class="col-auto">
                    <span class="badge bg-primary fs-6">
                        <i class="fas fa-flag me-1"></i>{{ $country->name }}
                    </span>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Extraction Warnings --}}
    @if(isset($extractedData['extraction_meta']['error']))
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Warning:</strong> {{ $extractedData['extraction_meta']['error'] }}
    </div>
    @endif

    <form action="{{ route('invoices.confirm') }}" method="POST" id="reviewForm">
        @csrf

        {{-- Invoice Header Info --}}
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2 text-primary"></i>Invoice Details</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Invoice Number</label>
                        <input type="text" class="form-control" name="invoice_number" 
                               value="{{ $extractedData['invoice_number'] ?? '' }}" 
                               placeholder="e.g., INV-2024-001">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Invoice Date</label>
                        <input type="date" class="form-control" name="invoice_date" 
                               value="{{ $extractedData['invoice_date'] ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Currency</label>
                        <input type="text" class="form-control bg-light" 
                               value="{{ $extractedData['currency'] ?? 'USD' }}" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Extracted Total</label>
                        <input type="text" class="form-control bg-light" 
                               value="{{ $extractedData['total_amount'] ? number_format($extractedData['total_amount'], 2) : 'N/A' }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        {{-- Line Items --}}
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i>Line Items ({{ count($extractedData['items'] ?? []) }})</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addItemBtn">
                    <i class="fas fa-plus me-1"></i>Add Item
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px">#</th>
                                <th style="width: 100px">SKU</th>
                                <th style="width: 100px">Item #</th>
                                <th>Description <span class="text-danger">*</span></th>
                                <th style="width: 100px">Qty</th>
                                <th style="width: 120px">Unit Price</th>
                                <th style="width: 120px">Total</th>
                                <th style="width: 50px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($extractedData['items'] ?? [] as $index => $item)
                            <tr class="item-row" data-index="{{ $index }}">
                                <td class="align-middle text-center text-muted">{{ $index + 1 }}</td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" 
                                           name="items[{{ $index }}][sku]" 
                                           value="{{ $item['sku'] ?? '' }}"
                                           placeholder="SKU">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" 
                                           name="items[{{ $index }}][item_number]" 
                                           value="{{ $item['item_number'] ?? '' }}"
                                           placeholder="Item #">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm description-input" 
                                           name="items[{{ $index }}][description]" 
                                           value="{{ $item['description'] ?? '' }}" 
                                           required
                                           placeholder="Item description (required)">
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm quantity-input" 
                                           name="items[{{ $index }}][quantity]" 
                                           value="{{ $item['quantity'] ?? 1 }}" 
                                           min="0" step="0.001"
                                           placeholder="Qty">
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control unit-price-input" 
                                               name="items[{{ $index }}][unit_price]" 
                                               value="{{ $item['unit_price'] ?? '' }}" 
                                               min="0" step="0.01"
                                               placeholder="0.00">
                                    </div>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm bg-light total-price-display" 
                                           value="{{ number_format(($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0), 2) }}" 
                                           readonly>
                                </td>
                                <td class="align-middle">
                                    <button type="button" class="btn btn-sm btn-link text-danger remove-item-btn p-0" title="Remove item">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr id="noItemsRow">
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                    No items extracted. Click "Add Item" to manually enter items.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="6" class="text-end fw-bold">Calculated Total:</td>
                                <td>
                                    <span id="grandTotal" class="fw-bold">$0.00</span>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="d-flex justify-content-between align-items-center mt-4">
            <div class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Review and correct the extracted data, then proceed to AI classification.
            </div>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-robot me-2"></i>Proceed to Classification
            </button>
        </div>
    </form>
</div>

{{-- Item Row Template (hidden) --}}
<template id="itemRowTemplate">
    <tr class="item-row" data-index="__INDEX__">
        <td class="align-middle text-center text-muted">__NUM__</td>
        <td>
            <input type="text" class="form-control form-control-sm" 
                   name="items[__INDEX__][sku]" 
                   placeholder="SKU">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm" 
                   name="items[__INDEX__][item_number]" 
                   placeholder="Item #">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm description-input" 
                   name="items[__INDEX__][description]" 
                   required
                   placeholder="Item description (required)">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm quantity-input" 
                   name="items[__INDEX__][quantity]" 
                   value="1" 
                   min="0" step="0.001"
                   placeholder="Qty">
        </td>
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control unit-price-input" 
                       name="items[__INDEX__][unit_price]" 
                       min="0" step="0.01"
                       placeholder="0.00">
            </div>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm bg-light total-price-display" 
                   value="0.00" 
                   readonly>
        </td>
        <td class="align-middle">
            <button type="button" class="btn btn-sm btn-link text-danger remove-item-btn p-0" title="Remove item">
                <i class="fas fa-times"></i>
            </button>
        </td>
    </tr>
</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('itemsTable');
    const tbody = table.querySelector('tbody');
    const addItemBtn = document.getElementById('addItemBtn');
    const template = document.getElementById('itemRowTemplate');
    const grandTotalEl = document.getElementById('grandTotal');
    const noItemsRow = document.getElementById('noItemsRow');

    // Calculate totals
    function updateTotals() {
        let grandTotal = 0;
        const rows = tbody.querySelectorAll('.item-row');
        
        rows.forEach((row, index) => {
            const qty = parseFloat(row.querySelector('.quantity-input')?.value) || 0;
            const price = parseFloat(row.querySelector('.unit-price-input')?.value) || 0;
            const total = qty * price;
            
            const totalDisplay = row.querySelector('.total-price-display');
            if (totalDisplay) {
                totalDisplay.value = total.toFixed(2);
            }
            
            grandTotal += total;
        });
        
        grandTotalEl.textContent = '$' + grandTotal.toFixed(2);
    }

    // Reindex rows after add/remove
    function reindexRows() {
        const rows = tbody.querySelectorAll('.item-row');
        rows.forEach((row, index) => {
            row.dataset.index = index;
            row.querySelector('td:first-child').textContent = index + 1;
            
            // Update input names
            row.querySelectorAll('input, select').forEach(input => {
                const name = input.name;
                if (name) {
                    input.name = name.replace(/items\[\d+\]/, `items[${index}]`);
                }
            });
        });
        
        // Show/hide no items row
        if (noItemsRow) {
            noItemsRow.style.display = rows.length === 0 ? '' : 'none';
        }
    }

    // Add new item row
    addItemBtn.addEventListener('click', function() {
        const existingRows = tbody.querySelectorAll('.item-row');
        const newIndex = existingRows.length;
        
        let html = template.innerHTML
            .replace(/__INDEX__/g, newIndex)
            .replace(/__NUM__/g, newIndex + 1);
        
        // Remove no items row if present
        if (noItemsRow) {
            noItemsRow.style.display = 'none';
        }
        
        tbody.insertAdjacentHTML('beforeend', html);
        
        // Attach event listeners to new row
        const newRow = tbody.querySelector(`.item-row[data-index="${newIndex}"]`);
        attachRowListeners(newRow);
        
        // Focus on description input
        newRow.querySelector('.description-input').focus();
        
        updateTotals();
    });

    // Attach listeners to a row
    function attachRowListeners(row) {
        const qtyInput = row.querySelector('.quantity-input');
        const priceInput = row.querySelector('.unit-price-input');
        const removeBtn = row.querySelector('.remove-item-btn');
        
        if (qtyInput) {
            qtyInput.addEventListener('input', updateTotals);
        }
        if (priceInput) {
            priceInput.addEventListener('input', updateTotals);
        }
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                row.remove();
                reindexRows();
                updateTotals();
            });
        }
    }

    // Attach listeners to existing rows
    tbody.querySelectorAll('.item-row').forEach(row => {
        attachRowListeners(row);
    });

    // Initial total calculation
    updateTotals();
});
</script>
@endpush
