@extends('layouts.app')

@section('title', 'Declaration Form - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-alt me-2"></i>Declaration Form</h2>
        <div class="btn-group">
            @if($availableTemplatesCount > 0)
                <a href="{{ route('declaration-forms.select-templates', $declarationForm) }}" class="btn btn-primary">
                    <i class="fas fa-file-export me-2"></i>Generate Official Forms
                </a>
            @else
                <button class="btn btn-outline-secondary" disabled title="No form templates available for this country">
                    <i class="fas fa-file-export me-2"></i>No Templates Available
                </button>
            @endif
            @php
                $webTargets = \App\Models\WebFormTarget::active()->where('country_id', $declarationForm->country_id)->count();
            @endphp
            @if($webTargets > 0)
                <a href="{{ route('web-submission.index', $declarationForm) }}" class="btn btn-success">
                    <i class="fas fa-cloud-upload-alt me-2"></i>Submit to Portal
                </a>
            @endif
            <a href="{{ route('declaration-forms.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <!-- Declaration Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-info-circle me-2"></i>Declaration Details</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2"><strong>Form Number:</strong> {{ $declarationForm->form_number }}</div>
                            <div class="mb-2"><strong>Date:</strong> {{ $declarationForm->declaration_date?->format('Y-m-d') }}</div>
                            <div class="mb-2"><strong>Country:</strong> {{ $declarationForm->country?->name ?? 'N/A' }}</div>
                        </div>
                        <div class="col-md-6">
                            @php
                                $allInvoices = $declarationForm->getAllInvoices();
                            @endphp
                            <div class="mb-2"><strong>Invoice{{ $allInvoices->count() > 1 ? 's' : '' }}:</strong>
                                @if($allInvoices->count() > 0)
                                    @foreach($allInvoices as $inv)
                                        <a href="{{ route('invoices.show', $inv) }}">#{{ $inv->invoice_number }}</a>@if(!$loop->last), @endif
                                    @endforeach
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </div>
                            @if($declarationForm->shipment)
                            <div class="mb-2"><strong>Shipment:</strong>
                                <a href="{{ route('shipments.show', $declarationForm->shipment) }}">
                                    {{ $declarationForm->shipment->primary_document_number ?? 'SHIP-'.$declarationForm->shipment->id }}
                                </a>
                            </div>
                            @endif
                            <div class="mb-2"><strong>Source:</strong> {{ ucfirst($declarationForm->source_type ?? 'generated') }}</div>
                        </div>
                    </div>
                    
                    {{-- Shipping Details --}}
                    @if($declarationForm->bill_of_lading_number || $declarationForm->manifest_number || $declarationForm->carrier_name)
                    <hr>
                    <h6 class="text-muted mb-3"><i class="fas fa-ship me-2"></i>Shipping Details</h6>
                    <div class="row">
                        <div class="col-md-6">
                            @if($declarationForm->bill_of_lading_number)
                            <div class="mb-2"><strong>B/L Number:</strong> {{ $declarationForm->bill_of_lading_number }}</div>
                            @endif
                            @if($declarationForm->manifest_number)
                            <div class="mb-2"><strong>Manifest #:</strong> {{ $declarationForm->manifest_number }}</div>
                            @endif
                            @if($declarationForm->carrier_name)
                            <div class="mb-2"><strong>Carrier:</strong> {{ $declarationForm->carrier_name }}</div>
                            @endif
                            @if($declarationForm->vessel_name)
                            <div class="mb-2"><strong>Vessel:</strong> {{ $declarationForm->vessel_name }}</div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            @if($declarationForm->port_of_loading)
                            <div class="mb-2"><strong>Port of Loading:</strong> {{ $declarationForm->port_of_loading }}</div>
                            @endif
                            @if($declarationForm->port_of_arrival)
                            <div class="mb-2"><strong>Port of Arrival:</strong> {{ $declarationForm->port_of_arrival }}</div>
                            @endif
                            @if($declarationForm->arrival_date)
                            <div class="mb-2"><strong>Arrival Date:</strong> {{ $declarationForm->arrival_date->format('M d, Y') }}</div>
                            @endif
                            @if($declarationForm->total_packages)
                            <div class="mb-2"><strong>Packages:</strong> {{ $declarationForm->total_packages }} {{ $declarationForm->package_type ?? '' }}</div>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- Trade Contacts --}}
                    @if($declarationForm->shipperContact || $declarationForm->consigneeContact)
                    <hr>
                    <h6 class="text-muted mb-3"><i class="fas fa-users me-2"></i>Parties</h6>
                    <div class="row">
                        @if($declarationForm->shipperContact)
                        <div class="col-md-6">
                            <div class="mb-2"><strong>Shipper:</strong></div>
                            <div class="small">
                                {{ $declarationForm->shipperContact->company_name }}<br>
                                <span class="text-muted">{{ $declarationForm->shipperContact->full_address }}</span>
                            </div>
                        </div>
                        @endif
                        @if($declarationForm->consigneeContact)
                        <div class="col-md-6">
                            <div class="mb-2"><strong>Consignee:</strong></div>
                            <div class="small">
                                {{ $declarationForm->consigneeContact->company_name }}<br>
                                <span class="text-muted">{{ $declarationForm->consigneeContact->full_address }}</span>
                            </div>
                        </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            {{-- Value & Duty Calculation Card --}}
            @if($declarationForm->cif_value || $declarationForm->customs_duty_total)
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-calculator me-2"></i>Value & Duty Calculation</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Value Summary</h6>
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td>FOB Value</td>
                                    <td class="text-end">${{ number_format((float)($declarationForm->fob_value ?? 0), 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Freight @if($declarationForm->freight_prorated)<small class="text-muted">(prorated)</small>@endif</td>
                                    <td class="text-end">${{ number_format((float)($declarationForm->freight_total ?? 0), 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Insurance @if($declarationForm->insurance_prorated)<small class="text-muted">(prorated)</small>@endif</td>
                                    <td class="text-end">${{ number_format((float)($declarationForm->insurance_total ?? 0), 2) }}</td>
                                </tr>
                                <tr class="table-primary fw-bold">
                                    <td>CIF Value</td>
                                    <td class="text-end">${{ number_format((float)($declarationForm->cif_value ?? 0), 2) }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Duties & Levies</h6>
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td>Customs Duty (CUD)</td>
                                    <td class="text-end">${{ number_format((float)($declarationForm->customs_duty_total ?? 0), 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Wharfage (WHA)</td>
                                    <td class="text-end">${{ number_format((float)($declarationForm->wharfage_total ?? 0), 2) }}</td>
                                </tr>
                                @if($declarationForm->other_levies_total > 0)
                                <tr>
                                    <td>Other Levies</td>
                                    <td class="text-end">${{ number_format((float)($declarationForm->other_levies_total ?? 0), 2) }}</td>
                                </tr>
                                @endif
                                <tr class="table-success fw-bold">
                                    <td>Total Payable</td>
                                    <td class="text-end">${{ number_format((float)($declarationForm->total_duty ?? 0), 2) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    {{-- Duty Breakdown by Tariff --}}
                    @if($declarationForm->duty_breakdown && count($declarationForm->duty_breakdown) > 0)
                    <hr>
                    <h6 class="text-muted mb-3">Breakdown by Tariff Code</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tariff Code</th>
                                    <th>Description</th>
                                    <th class="text-center">Rate</th>
                                    <th class="text-end">CIF</th>
                                    <th class="text-end">Duty</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($declarationForm->duty_breakdown as $row)
                                <tr>
                                    <td><code>{{ $row['tariff_code'] ?? '-' }}</code></td>
                                    <td>{{ Str::limit($row['tariff_description'] ?? '-', 40) }}</td>
                                    <td class="text-center">{{ number_format($row['duty_rate'] ?? 0, 1) }}%</td>
                                    <td class="text-end">${{ number_format($row['total_cif'] ?? 0, 2) }}</td>
                                    <td class="text-end">${{ number_format($row['total_duty'] ?? 0, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    {{-- Levy Breakdown --}}
                    @if($declarationForm->levy_breakdown && count($declarationForm->levy_breakdown) > 0)
                    <hr>
                    <h6 class="text-muted mb-3">Levy Details</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Levy</th>
                                    <th>Rate</th>
                                    <th>Basis</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($declarationForm->levy_breakdown as $levy)
                                <tr>
                                    <td>{{ $levy['levy_name'] ?? $levy['levy_code'] ?? '-' }}</td>
                                    <td>{{ $levy['formatted_rate'] ?? number_format($levy['rate'] ?? 0, 2) . '%' }}</td>
                                    <td>{{ strtoupper($levy['calculation_basis'] ?? 'FOB') }}</td>
                                    <td class="text-end">${{ number_format($levy['amount'] ?? 0, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Line Items -->
            <div class="card">
                <div class="card-header">
                    <strong><i class="fas fa-list me-2"></i>Line Items</strong>
                </div>
                <div class="card-body">
                    @if($items->count() === 0)
                        <p class="text-muted mb-0">No normalized items available for this declaration.</p>
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
                                                <code>{{ $item->hs_code ?? '-' }}</code>
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

        <div class="col-lg-4">
            <!-- Generated Official Forms -->
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-file-pdf me-2"></i>Generated Official Forms</strong>
                </div>
                <div class="card-body">
                    @if($filledForms->count() > 0)
                        <ul class="list-group list-group-flush">
                            @foreach($filledForms as $filledForm)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <div class="fw-bold">{{ $filledForm->template?->name ?? 'Unknown Form' }}</div>
                                        <small class="text-muted">{{ $filledForm->created_at->format('M d, Y H:i') }}</small>
                                        <span class="badge bg-{{ $filledForm->status === 'complete' ? 'success' : ($filledForm->status === 'error' ? 'danger' : 'warning') }} ms-2">
                                            {{ $filledForm->status_label }}
                                        </span>
                                    </div>
                                    <a href="{{ route('declaration-forms.preview', [$declarationForm, $filledForm]) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="text-center py-3">
                            <i class="fas fa-file-alt fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-3">No official forms generated yet</p>
                            @if($availableTemplatesCount > 0)
                                <a href="{{ route('declaration-forms.select-templates', $declarationForm) }}" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus me-1"></i>Generate Forms
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Quick Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-info me-2"></i>Quick Info</strong>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Total Items</dt>
                        <dd>{{ $items->count() }}</dd>
                        
                        <dt>Available Templates</dt>
                        <dd>{{ $availableTemplatesCount }} form(s) for {{ $declarationForm->country?->name ?? 'N/A' }}</dd>
                        
                        @php
                            $quickInfoInvoices = $declarationForm->getAllInvoices();
                            $invoiceTotal = $quickInfoInvoices->sum('total_amount');
                        @endphp
                        @if($quickInfoInvoices->count() > 0)
                            <dt>Invoice{{ $quickInfoInvoices->count() > 1 ? 's' : '' }} Total</dt>
                            <dd>${{ number_format((float)$invoiceTotal, 2) }}
                                @if($quickInfoInvoices->count() > 1)
                                    <small class="text-muted">({{ $quickInfoInvoices->count() }} invoices)</small>
                                @endif
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Duty Summary Card --}}
            @if($declarationForm->total_duty)
            <div class="card bg-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>FOB</span>
                        <span>${{ number_format((float)($declarationForm->fob_value ?? 0), 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>+ Freight</span>
                        <span>${{ number_format((float)($declarationForm->freight_total ?? 0), 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>+ Insurance</span>
                        <span>${{ number_format((float)($declarationForm->insurance_total ?? 0), 2) }}</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-bold">CIF</span>
                        <span class="fw-bold">${{ number_format((float)($declarationForm->cif_value ?? 0), 2) }}</span>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold text-success">Total Duty</span>
                        <span class="fw-bold text-success fs-5">${{ number_format((float)($declarationForm->total_duty ?? 0), 2) }}</span>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
