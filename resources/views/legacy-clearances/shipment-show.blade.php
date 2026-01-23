@extends('layouts.app')

@section('title', 'Legacy Clearance Details - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-archive me-2"></i>Legacy Clearance Details</h2>
        <a href="{{ route('legacy-clearances.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Legacy Clearances
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        </div>
    @endif

    {{-- Shipment Overview --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <strong><i class="fas fa-ship me-2"></i>Shipment Details</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <div class="text-muted small">B/L / AWB Number</div>
                                <div class="fw-semibold">
                                    @if($shipment->bill_of_lading_number)
                                        <code>{{ $shipment->bill_of_lading_number }}</code>
                                    @elseif($shipment->awb_number)
                                        <code>{{ $shipment->awb_number }}</code>
                                    @else
                                        <span class="text-muted">Not provided</span>
                                    @endif
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="text-muted small">Country</div>
                                <div class="fw-semibold">{{ $shipment->country?->name ?? '-' }}</div>
                            </div>
                            <div class="mb-3">
                                <div class="text-muted small">Carrier</div>
                                <div class="fw-semibold">{{ $shipment->carrier_name ?? '-' }}</div>
                            </div>
                            <div class="mb-3">
                                <div class="text-muted small">Vessel</div>
                                <div class="fw-semibold">{{ $shipment->vessel_name ?? '-' }}</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <div class="text-muted small">Port of Loading</div>
                                <div class="fw-semibold">{{ $shipment->port_of_loading ?? '-' }}</div>
                            </div>
                            <div class="mb-3">
                                <div class="text-muted small">Port of Discharge</div>
                                <div class="fw-semibold">{{ $shipment->port_of_discharge ?? '-' }}</div>
                            </div>
                            <div class="mb-3">
                                <div class="text-muted small">Packages</div>
                                <div class="fw-semibold">
                                    {{ $shipment->total_packages ?? '-' }}
                                    @if($shipment->package_type)
                                        ({{ $shipment->package_type }})
                                    @endif
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="text-muted small">Gross Weight</div>
                                <div class="fw-semibold">
                                    @if($shipment->gross_weight_kg)
                                        {{ number_format((float) $shipment->gross_weight_kg, 2) }} kg
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <strong><i class="fas fa-dollar-sign me-2"></i>Financial Summary</strong>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 col-md-3 mb-3">
                            <div class="text-muted small">FOB Total</div>
                            <div class="fw-bold fs-5">${{ number_format((float) $shipment->fob_total, 2) }}</div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="text-muted small">Freight</div>
                            <div class="fw-bold fs-5">${{ number_format((float) $shipment->freight_total, 2) }}</div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="text-muted small">Insurance</div>
                            <div class="fw-bold fs-5">${{ number_format((float) $shipment->insurance_total, 2) }}</div>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="text-muted small">CIF Total</div>
                            <div class="fw-bold fs-5 text-success">${{ number_format((float) $shipment->cif_total, 2) }}</div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-6">
                            <div class="text-muted small">Invoices</div>
                            <div class="fw-semibold">{{ $shipment->invoices->count() }}</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Imported On</div>
                            <div class="fw-semibold">{{ $shipment->created_at->format('Y-m-d H:i') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Invoices --}}
    <div class="card mb-4">
        <div class="card-header">
            <strong><i class="fas fa-file-invoice me-2"></i>Invoices ({{ $shipment->invoices->count() }})</strong>
        </div>
        <div class="card-body">
            @if($shipment->invoices->count() === 0)
                <p class="text-muted mb-0">No invoices linked to this clearance.</p>
            @else
                <div class="accordion" id="invoicesAccordion">
                    @foreach($shipment->invoices as $index => $invoice)
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button {{ $index > 0 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#invoice{{ $invoice->id }}">
                                    <span class="me-3">
                                        <i class="fas fa-receipt me-2"></i>
                                        {{ $invoice->invoice_number }}
                                    </span>
                                    <span class="badge bg-secondary me-2">{{ $invoice->invoiceItems->count() }} items</span>
                                    <span class="text-muted">${{ number_format((float) $invoice->total_amount, 2) }}</span>
                                </button>
                            </h2>
                            <div id="invoice{{ $invoice->id }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" data-bs-parent="#invoicesAccordion">
                                <div class="accordion-body">
                                    <div class="mb-3">
                                        <span class="text-muted">Date:</span> {{ $invoice->invoice_date?->format('Y-m-d') }}
                                        <a href="{{ route('legacy-clearances.invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary ms-3">
                                            <i class="fas fa-external-link-alt me-1"></i>View Full Invoice
                                        </a>
                                    </div>
                                    @if($invoice->invoiceItems->count() > 0)
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Description</th>
                                                        <th>Qty</th>
                                                        <th>Unit Price</th>
                                                        <th>Matched HS Code</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($invoice->invoiceItems as $item)
                                                        @php
                                                            $match = $matches->get($item->id);
                                                            $declItem = $match?->declarationFormItem;
                                                        @endphp
                                                        <tr>
                                                            <td>{{ $item->line_number ?? '-' }}</td>
                                                            <td>{{ Str::limit($item->description, 60) }}</td>
                                                            <td>{{ $item->quantity ?? '-' }}</td>
                                                            <td>
                                                                @if($item->unit_price !== null)
                                                                    ${{ number_format((float) $item->unit_price, 2) }}
                                                                @else
                                                                    -
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if($declItem && $declItem->hs_code)
                                                                    <code>{{ $declItem->hs_code }}</code>
                                                                    @if($match)
                                                                        <span class="badge bg-success ms-1">{{ $match->confidence }}%</span>
                                                                    @endif
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
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Declaration Forms --}}
    <div class="card mb-4">
        <div class="card-header">
            <strong><i class="fas fa-stamp me-2"></i>Declarations (Approved HS Codes)</strong>
        </div>
        <div class="card-body">
            @if($shipment->declarationForms->count() === 0)
                <p class="text-muted mb-0">No declarations linked to this clearance.</p>
            @else
                @foreach($shipment->declarationForms as $declaration)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="mb-1">
                                    <i class="fas fa-file-alt me-2 text-success"></i>
                                    {{ $declaration->form_number }}
                                </h6>
                                <div class="text-muted small">
                                    Date: {{ $declaration->declaration_date?->format('Y-m-d') }}
                                    | Total Duty: ${{ number_format((float) $declaration->total_duty, 2) }}
                                </div>
                            </div>
                            <a href="{{ route('legacy-clearances.declarations.show', $declaration) }}" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-external-link-alt me-1"></i>View Full Declaration
                            </a>
                        </div>
                        
                        @if($declaration->declarationFormItems->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Description</th>
                                            <th>HS Code</th>
                                            <th>HS Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($declaration->declarationFormItems as $item)
                                            <tr>
                                                <td>{{ $item->line_number ?? '-' }}</td>
                                                <td>{{ Str::limit($item->description, 50) }}</td>
                                                <td><code class="fs-6">{{ $item->hs_code ?? '-' }}</code></td>
                                                <td class="text-muted small">{{ Str::limit($item->hs_description, 40) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- Shipping Documents --}}
    @if($shipment->shippingDocuments->count() > 0)
        <div class="card">
            <div class="card-header">
                <strong><i class="fas fa-file-alt me-2"></i>Shipping Documents</strong>
            </div>
            <div class="card-body">
                <ul class="list-group">
                    @foreach($shipment->shippingDocuments as $doc)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-file me-2"></i>
                                <strong>{{ $doc->getDocumentTypeLabel() }}</strong>
                                @if($doc->document_number)
                                    — <code>{{ $doc->document_number }}</code>
                                @endif
                                <span class="text-muted ms-2">({{ $doc->file_name }})</span>
                            </div>
                            @if($doc->is_verified)
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Verified</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</div>
@endsection
