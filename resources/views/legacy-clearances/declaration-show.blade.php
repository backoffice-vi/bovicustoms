@extends('layouts.app')

@section('title', 'Legacy Declaration - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-alt me-2"></i>Legacy Trade Declaration</h2>
        <a href="{{ route('legacy-clearances.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-2"><strong>Form #:</strong> {{ $declarationForm->form_number }}</div>
                    <div class="mb-2"><strong>Date:</strong> {{ $declarationForm->declaration_date?->format('Y-m-d') }}</div>
                    <div class="mb-2"><strong>Total Duty:</strong> ${{ number_format((float) $declarationForm->total_duty, 2) }}</div>
                </div>
                <div class="col-md-6">
                    @if($declarationForm->invoice_id)
                    <div class="mb-2"><strong>Invoice:</strong>
                        <a href="{{ route('legacy-clearances.invoices.show', $declarationForm->invoice_id) }}">#{{ $declarationForm->invoice?->invoice_number ?? $declarationForm->invoice_id }}</a>
                    </div>
                    @endif
                    <div class="mb-2"><strong>Source:</strong> {{ ucfirst($declarationForm->source_type ?? 'generated') }}</div>
                    <div class="mb-2"><strong>Country:</strong> {{ $declarationForm->country?->name ?? $declarationForm->country_id }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Declaration Line Items (Approved HS Codes)</strong>
        </div>
        <div class="card-body">
            @if($items->count() === 0)
                <p class="text-muted mb-0">No declaration items available.</p>
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
                                <th>HS Code</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
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
                                        <code class="fs-6">{{ $item->hs_code ?? '-' }}</code>
                                        @if($item->hs_description)
                                            <div class="text-muted small">{{ $item->hs_description }}</div>
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
