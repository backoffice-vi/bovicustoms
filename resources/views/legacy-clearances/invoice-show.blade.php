@extends('layouts.app')

@section('title', 'Legacy Invoice - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-receipt me-2"></i>Legacy Invoice</h2>
        <a href="{{ route('legacy-clearances.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-2"><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</div>
                    <div class="mb-2"><strong>Date:</strong> {{ $invoice->invoice_date?->format('Y-m-d') }}</div>
                    <div class="mb-2"><strong>Total:</strong>
                        @if($invoice->total_amount !== null)
                            ${{ number_format((float) $invoice->total_amount, 2) }}
                        @else
                            <span class="text-muted">N/A</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-2"><strong>Status:</strong> {{ ucfirst($invoice->status) }}</div>
                    <div class="mb-2"><strong>Source:</strong> {{ ucfirst($invoice->source_type ?? 'generated') }}</div>
                    <div class="mb-2"><strong>Country:</strong> {{ $invoice->country?->name ?? $invoice->country_id }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Invoice Items + Approved HS Codes</strong>
        </div>
        <div class="card-body">
            @if($items->count() === 0)
                <p class="text-muted mb-0">No invoice items available.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Description</th>
                                <th>SKU</th>
                                <th>Item #</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Matched HS Code</th>
                                <th>Confidence</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                @php
                                    $match = $matches->get($item->id);
                                    $declItem = $match?->declarationFormItem;
                                @endphp
                                <tr>
                                    <td>{{ $item->line_number ?? '-' }}</td>
                                    <td>{{ $item->description }}</td>
                                    <td>{{ $item->sku ?? '-' }}</td>
                                    <td>{{ $item->item_number ?? '-' }}</td>
                                    <td>{{ $item->quantity ?? '-' }}</td>
                                    <td>
                                        @if($item->unit_price !== null)
                                            {{ $item->currency ?? '$' }}{{ number_format((float) $item->unit_price, 2) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($declItem)
                                            <code>{{ $declItem->hs_code ?? '-' }}</code>
                                            @if($declItem->hs_description)
                                                <div class="text-muted small">{{ $declItem->hs_description }}</div>
                                            @endif
                                            <div class="small">
                                                <a href="{{ route('legacy-clearances.declarations.show', $declItem->declaration_form_id) }}">View declaration</a>
                                            </div>
                                        @else
                                            <span class="text-muted">Unmatched</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($match)
                                            <span class="badge bg-primary">{{ $match->confidence }}%</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
