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
        $hasUnclassifiedItems = $items->contains(fn($item) => empty($item->customs_code));
        $isStuck = $invoice->status === 'classifying' || ($invoice->status !== 'processed' && $hasUnclassifiedItems);
    @endphp
    
    @if($isStuck)
    <div class="alert alert-warning d-flex align-items-center justify-content-between" role="alert">
        <div>
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Classification incomplete.</strong> 
            @if($invoice->status === 'classifying')
                This invoice is still being classified or the classification job may have failed.
            @else
                Some items don't have HS codes assigned.
            @endif
        </div>
        <form action="{{ route('invoices.retry_classification', $invoice) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-warning">
                <i class="fas fa-redo me-1"></i>Retry Classification
            </button>
        </form>
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
                            'pending' => 'warning',
                            'classifying' => 'info',
                            'draft' => 'secondary',
                            default => 'primary'
                        };
                    @endphp
                    <span class="badge bg-{{ $statusClass }} fs-6">{{ ucfirst($invoice->status) }}</span>
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
                            <td>
                                @if($item->customs_code)
                                    <code class="fw-bold text-primary">{{ $item->customs_code }}</code>
                                @else
                                    <span class="text-muted">Not assigned</span>
                                @endif
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
    .btn, nav, .alert {
        display: none !important;
    }
    .card {
        border: 1px solid #ddd !important;
    }
}
</style>
@endpush
