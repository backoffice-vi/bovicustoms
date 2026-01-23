@extends('layouts.app')

@section('title', 'Legacy Clearances - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-archive me-2"></i>Legacy Clearances</h2>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
            <i class="fas fa-home me-2"></i>Dashboard
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <strong><i class="fas fa-upload me-2"></i>Import Legacy Clearance</strong>
            <div class="small opacity-75">Upload your historical clearance documents together to create a reference for future classifications.</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('legacy-clearances.upload') }}" enctype="multipart/form-data">
                @csrf
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Country <span class="text-danger">*</span></label>
                        <select class="form-select" name="country_id" required>
                            <option value="">Select country...</option>
                            @foreach($countries as $country)
                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">The country where this clearance was processed.</div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-file-invoice me-1 text-primary"></i>
                            Invoice(s) <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control" name="invoice_files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls">
                        <div class="form-text">Upload one or more invoice files (PDF, image, or Excel).</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-ship me-1 text-info"></i>
                            Shipping Document (B/L or AWB)
                        </label>
                        <input type="file" class="form-control" name="shipping_document" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text">Optional. Bill of Lading or Air Waybill for shipping details.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-stamp me-1 text-success"></i>
                            Cleared Declaration <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control" name="declaration_file" required accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls">
                        <div class="form-text">The customs declaration with approved HS codes.</div>
                    </div>
                </div>

                <div class="alert alert-info mt-4 mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Tip:</strong> For scanned PDFs that don't extract well, try uploading as images (JPG/PNG) instead.
                </div>

                <div class="mt-4">
                    <button class="btn btn-primary btn-lg" type="submit">
                        <i class="fas fa-cloud-upload-alt me-2"></i>Import Legacy Clearance
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <strong><i class="fas fa-search me-2"></i>Search Classification Reference</strong>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('legacy-clearances.index') }}" class="row g-3 align-items-end">
                <div class="col-md-7">
                    <label class="form-label">Description / Keyword</label>
                    <input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="e.g., iPhone, laptop, brake pads...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">HS Code</label>
                    <input type="text" name="hs_code" class="form-control" value="{{ $hs }}" placeholder="e.g., 8471">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary w-100" type="submit">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                </div>
            </form>

            @if($q !== '' || $hs !== '')
                <hr>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h6 class="text-muted text-uppercase">Invoice Items</h6>
                        @if($invoiceItemResults->count() === 0)
                            <div class="text-muted">No matches.</div>
                        @else
                            <ul class="list-group">
                                @foreach($invoiceItemResults as $item)
                                    <li class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <div class="me-3">
                                                <div class="fw-semibold">{{ $item->description }}</div>
                                                <div class="text-muted small">SKU: {{ $item->sku ?? '-' }} | Item#: {{ $item->item_number ?? '-' }}</div>
                                            </div>
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('legacy-clearances.invoices.show', $item->invoice_id) }}">View</a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="col-lg-6">
                        <h6 class="text-muted text-uppercase">Declaration Items (Approved HS)</h6>
                        @if($declarationItemResults->count() === 0)
                            <div class="text-muted">No matches.</div>
                        @else
                            <ul class="list-group">
                                @foreach($declarationItemResults as $item)
                                    <li class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <div class="me-3">
                                                <div class="fw-semibold">{{ $item->description }}</div>
                                                <div class="text-muted small">
                                                    HS: <code>{{ $item->hs_code ?? '-' }}</code>
                                                    @if($item->hs_description)
                                                        — {{ $item->hs_description }}
                                                    @endif
                                                </div>
                                            </div>
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('legacy-clearances.declarations.show', $item->declaration_form_id) }}">View</a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong><i class="fas fa-history me-2"></i>Recent Legacy Clearances</strong>
        </div>
        <div class="card-body">
            @if($recentLegacyShipments->count() === 0)
                <div class="text-muted text-center py-4">
                    <i class="fas fa-inbox fa-3x mb-3 text-secondary"></i>
                    <p class="mb-0">No legacy clearances imported yet. Upload your first one above!</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Country</th>
                                <th>B/L / AWB</th>
                                <th>Invoices</th>
                                <th>FOB Total</th>
                                <th>Items</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentLegacyShipments as $shipment)
                                <tr>
                                    <td>{{ $shipment->created_at->format('Y-m-d') }}</td>
                                    <td>{{ $shipment->country?->name ?? '-' }}</td>
                                    <td>
                                        @if($shipment->bill_of_lading_number)
                                            <code>{{ $shipment->bill_of_lading_number }}</code>
                                        @elseif($shipment->awb_number)
                                            <code>{{ $shipment->awb_number }}</code>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $shipment->invoices->count() }}</td>
                                    <td>${{ number_format((float) $shipment->fob_total, 2) }}</td>
                                    <td>
                                        @php
                                            $itemCount = $shipment->invoices->sum(fn($inv) => $inv->invoiceItems->count());
                                        @endphp
                                        {{ $itemCount }} items
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('legacy-clearances.shipments.show', $shipment) }}">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
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
