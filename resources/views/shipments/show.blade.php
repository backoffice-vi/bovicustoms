@extends('layouts.app')

@section('title', 'Shipment Details - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('shipments.index') }}">Shipments</a></li>
                <li class="breadcrumb-item active">{{ $shipment->primary_document_number ?? 'SHIP-'.$shipment->id }}</li>
            </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1">
                    <i class="fas fa-ship me-2"></i>
                    {{ $shipment->primary_document_number ?? 'Shipment #'.$shipment->id }}
                </h2>
                <p class="text-muted mb-0">
                    <span class="badge bg-{{ $shipment->status_color }}">{{ $shipment->status_label }}</span>
                    &middot; Created {{ $shipment->created_at->format('M d, Y') }}
                </p>
            </div>
            <div>
                @if($shipment->canGenerateDeclaration())
                <form action="{{ route('shipments.generate-declaration', $shipment) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-file-alt me-2"></i>Generate Declaration
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row">
        {{-- Left Column --}}
        <div class="col-lg-8">
            {{-- Invoices Card --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Invoices ({{ $shipment->invoices->count() }})</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addInvoicesModal">
                        <i class="fas fa-plus me-1"></i>Add Invoice
                    </button>
                </div>
                <div class="card-body p-0">
                    @if($shipment->invoices->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th class="text-end">FOB Value</th>
                                    <th class="text-end">Freight Share</th>
                                    <th class="text-end">Insurance Share</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($shipment->invoices as $invoice)
                                <tr>
                                    <td>
                                        <a href="{{ route('invoices.show', $invoice) }}" class="fw-semibold text-decoration-none">
                                            {{ $invoice->invoice_number }}
                                        </a>
                                    </td>
                                    <td>{{ $invoice->invoice_date?->format('M d, Y') ?? 'N/A' }}</td>
                                    <td class="text-end">${{ number_format($invoice->pivot->invoice_fob ?? $invoice->total_amount, 2) }}</td>
                                    <td class="text-end">${{ number_format($invoice->pivot->prorated_freight ?? 0, 2) }}</td>
                                    <td class="text-end">${{ number_format($invoice->pivot->prorated_insurance ?? 0, 2) }}</td>
                                    <td>
                                        <form action="{{ route('shipments.remove-invoice', [$shipment, $invoice]) }}" method="POST" 
                                              onsubmit="return confirm('Remove this invoice from the shipment?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Remove">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p class="mb-0">No invoices in this shipment.</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Shipping Documents Card --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Shipping Documents</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                        <i class="fas fa-upload me-1"></i>Upload Document
                    </button>
                </div>
                <div class="card-body">
                    @if($shipment->shippingDocuments->count() > 0)
                    <div class="list-group">
                        @foreach($shipment->shippingDocuments as $doc)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="badge bg-info me-2">{{ $doc->document_type_label }}</span>
                                        <span class="fw-semibold">{{ $doc->display_number }}</span>
                                        <span class="badge bg-{{ $doc->extraction_status_color }} ms-2">
                                            {{ $doc->extraction_status_label }}
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        {{ $doc->original_filename }} &middot; {{ $doc->file_size_formatted }}
                                    </small>
                                    
                                    @if($doc->isCompleted())
                                    <div class="mt-2 small">
                                        @if($doc->carrier_name)
                                            <span class="me-3"><strong>Carrier:</strong> {{ $doc->carrier_name }}</span>
                                        @endif
                                        @if($doc->vessel_name)
                                            <span class="me-3"><strong>Vessel:</strong> {{ $doc->vessel_name }}</span>
                                        @endif
                                        @if($doc->freight_charges)
                                            <span class="me-3"><strong>Freight:</strong> ${{ number_format($doc->freight_charges, 2) }}</span>
                                        @endif
                                    </div>
                                    @endif
                                </div>
                                <div class="btn-group">
                                    @if($doc->isPending() || $doc->isFailed())
                                    <button type="button" class="btn btn-sm btn-outline-primary extract-btn" 
                                            data-doc-id="{{ $doc->id }}">
                                        <i class="fas fa-magic"></i> Extract
                                    </button>
                                    @endif
                                    @if($doc->isCompleted())
                                    <button type="button" class="btn btn-sm btn-outline-secondary edit-doc-btn" 
                                            data-doc-id="{{ $doc->id }}"
                                            data-bs-toggle="modal" data-bs-target="#editDocumentModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    @endif
                                    <a href="{{ route('shipping-documents.download', $doc) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <form action="{{ route('shipping-documents.destroy', $doc) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete this document?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-file-upload fa-3x mb-3"></i>
                        <h5>No Documents Yet</h5>
                        <p class="mb-3">Upload a Bill of Lading, Air Waybill, or other shipping document.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                            <i class="fas fa-upload me-2"></i>Upload Document
                        </button>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Duty Calculation Card --}}
            @if($calculation)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Duty Calculation</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Value Summary</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td>FOB Value</td>
                                    <td class="text-end">${{ number_format($calculation['fob_value'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Freight</td>
                                    <td class="text-end">${{ number_format($calculation['freight_total'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Insurance</td>
                                    <td class="text-end">${{ number_format($calculation['insurance_total'], 2) }}</td>
                                </tr>
                                <tr class="table-primary fw-bold">
                                    <td>CIF Value</td>
                                    <td class="text-end">${{ number_format($calculation['cif_value'], 2) }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Duties & Levies</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td>Customs Duty (CUD)</td>
                                    <td class="text-end">${{ number_format($calculation['customs_duty_total'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Wharfage (WHA)</td>
                                    <td class="text-end">${{ number_format($calculation['wharfage_total'], 2) }}</td>
                                </tr>
                                @if($calculation['other_levies_total'] > 0)
                                <tr>
                                    <td>Other Levies</td>
                                    <td class="text-end">${{ number_format($calculation['other_levies_total'], 2) }}</td>
                                </tr>
                                @endif
                                <tr class="table-success fw-bold">
                                    <td>Total Payable</td>
                                    <td class="text-end">${{ number_format($calculation['total_duty'], 2) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    @if(!empty($calculation['duty_breakdown']))
                    <hr>
                    <h6 class="text-muted mb-3">Breakdown by Tariff Code</h6>
                    
                    @foreach($calculation['duty_breakdown'] as $index => $row)
                    <div class="card mb-3 border">
                        <div class="card-header bg-light py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-secondary me-2">Record #{{ str_pad($index + 1, 4, '0', STR_PAD_LEFT) }}</span>
                                    <strong>Tariff:</strong> <code class="text-primary">{{ $row['tariff_code'] ?? 'Unclassified' }}</code>
                                </div>
                                <span class="badge bg-success">{{ $row['item_count'] }} item(s)</span>
                            </div>
                        </div>
                        <div class="card-body py-2">
                            <div class="row">
                                {{-- Left Column - Item Details --}}
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <td class="text-muted" style="width: 40%;">Description:</td>
                                            <td><strong>{{ $row['general_description'] ?? $row['tariff_description'] ?? 'General Merchandise' }}</strong></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Quantity:</td>
                                            <td>{{ number_format($row['total_quantity'], 2) }} units</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">F.O.B. Value:</td>
                                            <td>${{ number_format($row['total_fob'], 2) }}</td>
                                        </tr>
                                    </table>
                                </div>
                                
                                {{-- Right Column - Charges --}}
                                <div class="col-md-6">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <td class="text-muted" style="width: 50%;">Freight (FRT):</td>
                                            <td class="text-end">${{ number_format($row['total_freight'], 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Insurance (INS):</td>
                                            <td class="text-end">${{ number_format($row['total_insurance'], 2) }}</td>
                                        </tr>
                                        <tr class="table-info">
                                            <td><strong>C.I.F. Value:</strong></td>
                                            <td class="text-end"><strong>${{ number_format($row['total_cif'], 2) }}</strong></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            {{-- Tax Breakdown --}}
                            <div class="mt-2 pt-2 border-top">
                                <div class="row">
                                    <div class="col-12">
                                        <table class="table table-sm mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Tax</th>
                                                    <th class="text-end">Value</th>
                                                    <th class="text-center">Rate</th>
                                                    <th class="text-end">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {{-- Customs Duty (CUD) --}}
                                                <tr>
                                                    <td><strong>CUD</strong> <small class="text-muted">(Customs Duty)</small></td>
                                                    <td class="text-end">${{ number_format($row['total_cif'], 2) }}</td>
                                                    <td class="text-center">{{ number_format($row['duty_rate'], 2) }}%</td>
                                                    <td class="text-end"><strong>${{ number_format($row['total_duty'], 2) }}</strong></td>
                                                </tr>
                                                
                                                {{-- Dynamic Levies --}}
                                                @php
                                                    $totalLeviesForTariff = 0;
                                                    $levies = $calculation['levies'] ?? [];
                                                @endphp
                                                
                                                @foreach($levies as $levy)
                                                @php
                                                    // Calculate this levy's amount for this tariff group
                                                    $levyBaseValue = match($levy['calculation_basis'] ?? 'fob') {
                                                        'cif' => $row['total_cif'],
                                                        'duty' => $row['total_duty'],
                                                        default => $row['total_fob'],
                                                    };
                                                    
                                                    $levyAmount = match($levy['rate_type'] ?? 'percentage') {
                                                        'percentage' => $levyBaseValue * (($levy['rate'] ?? 0) / 100),
                                                        'fixed_amount' => ($levy['rate'] ?? 0) * ($row['total_fob'] / ($calculation['fob_value'] ?: 1)), // Prorate fixed amounts
                                                        'per_unit' => ($levy['rate'] ?? 0) * ($row['total_quantity'] ?? 1),
                                                        default => 0,
                                                    };
                                                    
                                                    $totalLeviesForTariff += $levyAmount;
                                                @endphp
                                                
                                                @if($levyAmount > 0)
                                                <tr>
                                                    <td><strong>{{ $levy['levy_code'] }}</strong> <small class="text-muted">({{ $levy['levy_name'] }})</small></td>
                                                    <td class="text-end">${{ number_format($levyBaseValue, 2) }}</td>
                                                    <td class="text-center">{{ $levy['formatted_rate'] ?? number_format($levy['rate'] ?? 0, 2) . '%' }}</td>
                                                    <td class="text-end"><strong>${{ number_format($levyAmount, 2) }}</strong></td>
                                                </tr>
                                                @endif
                                                @endforeach
                                            </tbody>
                                            <tfoot class="table-success">
                                                <tr>
                                                    <td colspan="3" class="text-end"><strong>Total Due:</strong></td>
                                                    <td class="text-end"><strong>${{ number_format($row['total_duty'] + $totalLeviesForTariff, 2) }}</strong></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                    @endif
                </div>
            </div>
            @endif
        </div>

        {{-- Right Column --}}
        <div class="col-lg-4">
            {{-- Shipment Details Card --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Shipment Details</h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editShipmentModal">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Country</dt>
                        <dd class="col-7">{{ $shipment->country?->name ?? '-' }}</dd>

                        <dt class="col-5">B/L Number</dt>
                        <dd class="col-7">{{ $shipment->bill_of_lading_number ?? '-' }}</dd>

                        <dt class="col-5">Manifest #</dt>
                        <dd class="col-7">{{ $shipment->manifest_number ?? '-' }}</dd>

                        <dt class="col-5">Carrier</dt>
                        <dd class="col-7">{{ $shipment->carrier_name ?? '-' }}</dd>

                        <dt class="col-5">Vessel</dt>
                        <dd class="col-7">{{ $shipment->vessel_name ?? '-' }}</dd>

                        <dt class="col-5">Port Loading</dt>
                        <dd class="col-7">{{ $shipment->port_of_loading ?? '-' }}</dd>

                        <dt class="col-5">Port Discharge</dt>
                        <dd class="col-7">{{ $shipment->port_of_discharge ?? '-' }}</dd>

                        <dt class="col-5">Packages</dt>
                        <dd class="col-7">
                            @if($shipment->total_packages)
                                {{ $shipment->total_packages }} {{ $shipment->package_type ?? 'pkgs' }}
                            @else
                                -
                            @endif
                        </dd>

                        <dt class="col-5">Weight</dt>
                        <dd class="col-7">
                            @if($shipment->gross_weight_kg)
                                {{ number_format($shipment->gross_weight_kg, 2) }} kg
                            @else
                                -
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            {{-- Trade Contacts Card --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-users me-2"></i>Trade Contacts</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Shipper</label>
                        <select class="form-select form-select-sm contact-select" data-type="shipper">
                            <option value="">Select Shipper...</option>
                            @foreach($shippers as $contact)
                                <option value="{{ $contact->id }}" {{ $shipment->shipper_contact_id == $contact->id ? 'selected' : '' }}>
                                    {{ $contact->company_name }}
                                </option>
                            @endforeach
                        </select>
                        @if($shipment->shipperContact)
                            <small class="text-muted">{{ $shipment->shipperContact->full_address }}</small>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Consignee</label>
                        <select class="form-select form-select-sm contact-select" data-type="consignee">
                            <option value="">Select Consignee...</option>
                            @foreach($consignees as $contact)
                                <option value="{{ $contact->id }}" {{ $shipment->consignee_contact_id == $contact->id ? 'selected' : '' }}>
                                    {{ $contact->company_name }}
                                </option>
                            @endforeach
                        </select>
                        @if($shipment->consigneeContact)
                            <small class="text-muted">{{ $shipment->consigneeContact->full_address }}</small>
                        @endif
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Notify Party</label>
                        <select class="form-select form-select-sm contact-select" data-type="notify_party">
                            <option value="">Select Notify Party...</option>
                            @foreach($notifyParties as $contact)
                                <option value="{{ $contact->id }}" {{ $shipment->notify_party_contact_id == $contact->id ? 'selected' : '' }}>
                                    {{ $contact->company_name }}
                                </option>
                            @endforeach
                        </select>
                        @if($shipment->notifyPartyContact)
                            <small class="text-muted">{{ $shipment->notifyPartyContact->full_address }}</small>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Value Summary Card --}}
            <div class="card mb-4 bg-light">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>FOB Total</span>
                        <span class="fw-bold">${{ number_format($shipment->fob_total, 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Freight</span>
                        <div class="d-flex align-items-center">
                            {{-- Display Mode --}}
                            <span id="freightDisplay">${{ number_format($shipment->freight_total, 2) }}</span>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-2 text-muted" id="editFreightBtn" title="Edit Freight">
                                <i class="fas fa-pencil-alt fa-xs"></i>
                            </button>
                            {{-- Edit Mode --}}
                            <div id="freightEditMode" class="d-none">
                                <div class="input-group input-group-sm" style="width: 140px;">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" 
                                           id="freightInput" value="{{ $shipment->freight_total }}">
                                    <button type="button" class="btn btn-success btn-sm" id="saveFreightBtn" title="Save">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelFreightBtn" title="Cancel">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Insurance</span>
                        <div class="d-flex align-items-center">
                            {{-- Display Mode --}}
                            <span id="insuranceDisplay">${{ number_format($shipment->insurance_total, 2) }}</span>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-2 text-muted" id="editInsuranceBtn" title="Edit Insurance">
                                <i class="fas fa-pencil-alt fa-xs"></i>
                            </button>
                            {{-- Edit Mode --}}
                            <div id="insuranceEditMode" class="d-none">
                                <div class="input-group input-group-sm" style="width: 140px;">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" 
                                           id="insuranceInput" value="{{ $shipment->insurance_total }}">
                                    <button type="button" class="btn btn-success btn-sm" id="saveInsuranceBtn" title="Save">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelInsuranceBtn" title="Cancel">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold">CIF Total</span>
                        <span class="fw-bold text-primary fs-5" id="cifTotalDisplay">${{ number_format($shipment->cif_total, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Declarations Card --}}
            @if($shipment->declarationForms->count() > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-contract me-2"></i>Declarations</h5>
                </div>
                <div class="list-group list-group-flush">
                    @foreach($shipment->declarationForms as $declaration)
                    <a href="{{ route('declaration-forms.show', $declaration) }}" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>{{ $declaration->form_number }}</span>
                            <small class="text-muted">{{ $declaration->declaration_date?->format('M d, Y') }}</small>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Delete Shipment --}}
            <form action="{{ route('shipments.destroy', $shipment) }}" method="POST"
                  onsubmit="return confirm('Are you sure you want to delete this shipment? This will also delete all shipping documents.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger w-100">
                    <i class="fas fa-trash me-2"></i>Delete Shipment
                </button>
            </form>
        </div>
    </div>
</div>

{{-- Upload Document Modal --}}
<div class="modal fade" id="uploadDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('shipping-documents.store', $shipment) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Upload Shipping Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="document_type" class="form-label">Document Type</label>
                        <select name="document_type" id="document_type" class="form-select" required>
                            <option value="bill_of_lading">Bill of Lading</option>
                            <option value="air_waybill">Air Waybill (AWB)</option>
                            <option value="packing_list">Packing List</option>
                            <option value="certificate_of_origin">Certificate of Origin</option>
                            <option value="insurance_certificate">Insurance Certificate</option>
                            <option value="commercial_invoice">Commercial Invoice</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="document" class="form-label">Document File</label>
                        <input type="file" name="document" id="document" class="form-control" required
                               accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <small class="text-muted">Accepted: PDF, JPG, PNG, WebP (max 20MB)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add Invoices Modal --}}
<div class="modal fade" id="addInvoicesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('shipments.add-invoices', $shipment) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Invoices to Shipment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="availableInvoicesContainer">
                        <p class="text-muted">Loading available invoices...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Selected
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Shipment Modal --}}
<div class="modal fade" id="editShipmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('shipments.update', $shipment) }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Shipment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">B/L Number</label>
                            <input type="text" name="bill_of_lading_number" class="form-control" 
                                   value="{{ $shipment->bill_of_lading_number }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Manifest Number</label>
                            <input type="text" name="manifest_number" class="form-control" 
                                   value="{{ $shipment->manifest_number }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Carrier</label>
                            <input type="text" name="carrier_name" class="form-control" 
                                   value="{{ $shipment->carrier_name }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vessel</label>
                            <input type="text" name="vessel_name" class="form-control" 
                                   value="{{ $shipment->vessel_name }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Port of Loading</label>
                            <input type="text" name="port_of_loading" class="form-control" 
                                   value="{{ $shipment->port_of_loading }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Port of Discharge</label>
                            <input type="text" name="port_of_discharge" class="form-control" 
                                   value="{{ $shipment->port_of_discharge }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Freight ($)</label>
                            <input type="number" step="0.01" name="freight_total" class="form-control" 
                                   value="{{ $shipment->freight_total }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ETA</label>
                            <input type="date" name="estimated_arrival_date" class="form-control" 
                                   value="{{ $shipment->estimated_arrival_date?->format('Y-m-d') }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Packages</label>
                            <input type="number" name="total_packages" class="form-control" 
                                   value="{{ $shipment->total_packages }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Package Type</label>
                            <input type="text" name="package_type" class="form-control" 
                                   value="{{ $shipment->package_type }}" placeholder="e.g., cartons, pallets">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gross Weight (kg)</label>
                        <input type="number" step="0.001" name="gross_weight_kg" class="form-control" 
                               value="{{ $shipment->gross_weight_kg }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const shipmentId = {{ $shipment->id }};
    
    // Inline Freight Editing
    const editFreightBtn = document.getElementById('editFreightBtn');
    const freightDisplay = document.getElementById('freightDisplay');
    const freightEditMode = document.getElementById('freightEditMode');
    const freightInput = document.getElementById('freightInput');
    const saveFreightBtn = document.getElementById('saveFreightBtn');
    const cancelFreightBtn = document.getElementById('cancelFreightBtn');
    const cifTotalDisplay = document.getElementById('cifTotalDisplay');
    
    let originalFreightValue = freightInput.value;
    
    // Show edit mode
    editFreightBtn.addEventListener('click', function() {
        freightDisplay.classList.add('d-none');
        editFreightBtn.classList.add('d-none');
        freightEditMode.classList.remove('d-none');
        freightInput.focus();
        freightInput.select();
        originalFreightValue = freightInput.value;
    });
    
    // Cancel edit
    cancelFreightBtn.addEventListener('click', function() {
        freightInput.value = originalFreightValue;
        freightDisplay.classList.remove('d-none');
        editFreightBtn.classList.remove('d-none');
        freightEditMode.classList.add('d-none');
    });
    
    // Save freight
    saveFreightBtn.addEventListener('click', saveFreight);
    
    // Save on Enter key
    freightInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveFreight();
        } else if (e.key === 'Escape') {
            cancelFreightBtn.click();
        }
    });
    
    async function saveFreight() {
        const newValue = parseFloat(freightInput.value) || 0;
        
        saveFreightBtn.disabled = true;
        saveFreightBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        try {
            const response = await fetch(`/shipments/${shipmentId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ freight_total: newValue })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update display
                freightDisplay.textContent = '$' + newValue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                originalFreightValue = newValue;
                
                // Switch back to display mode
                freightDisplay.classList.remove('d-none');
                editFreightBtn.classList.remove('d-none');
                freightEditMode.classList.add('d-none');
                
                // Reload page to update all calculations (CIF, duties, etc.)
                location.reload();
            } else {
                alert('Failed to update freight: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Save freight error:', error);
            alert('Failed to save freight: ' + error.message);
        } finally {
            saveFreightBtn.disabled = false;
            saveFreightBtn.innerHTML = '<i class="fas fa-check"></i>';
        }
    }
    
    // Inline Insurance Editing
    const editInsuranceBtn = document.getElementById('editInsuranceBtn');
    const insuranceDisplay = document.getElementById('insuranceDisplay');
    const insuranceEditMode = document.getElementById('insuranceEditMode');
    const insuranceInput = document.getElementById('insuranceInput');
    const saveInsuranceBtn = document.getElementById('saveInsuranceBtn');
    const cancelInsuranceBtn = document.getElementById('cancelInsuranceBtn');
    
    let originalInsuranceValue = insuranceInput.value;
    
    // Show edit mode
    editInsuranceBtn.addEventListener('click', function() {
        insuranceDisplay.classList.add('d-none');
        editInsuranceBtn.classList.add('d-none');
        insuranceEditMode.classList.remove('d-none');
        insuranceInput.focus();
        insuranceInput.select();
        originalInsuranceValue = insuranceInput.value;
    });
    
    // Cancel edit
    cancelInsuranceBtn.addEventListener('click', function() {
        insuranceInput.value = originalInsuranceValue;
        insuranceDisplay.classList.remove('d-none');
        editInsuranceBtn.classList.remove('d-none');
        insuranceEditMode.classList.add('d-none');
    });
    
    // Save insurance
    saveInsuranceBtn.addEventListener('click', saveInsurance);
    
    // Save on Enter key
    insuranceInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveInsurance();
        } else if (e.key === 'Escape') {
            cancelInsuranceBtn.click();
        }
    });
    
    async function saveInsurance() {
        const newValue = parseFloat(insuranceInput.value) || 0;
        
        saveInsuranceBtn.disabled = true;
        saveInsuranceBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        try {
            const response = await fetch(`/shipments/${shipmentId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ insurance_total: newValue })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update display
                insuranceDisplay.textContent = '$' + newValue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                originalInsuranceValue = newValue;
                
                // Switch back to display mode
                insuranceDisplay.classList.remove('d-none');
                editInsuranceBtn.classList.remove('d-none');
                insuranceEditMode.classList.add('d-none');
                
                // Reload page to update all calculations (CIF, duties, etc.)
                location.reload();
            } else {
                alert('Failed to update insurance: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Save insurance error:', error);
            alert('Failed to save insurance: ' + error.message);
        } finally {
            saveInsuranceBtn.disabled = false;
            saveInsuranceBtn.innerHTML = '<i class="fas fa-check"></i>';
        }
    }
    
    // Extract document buttons
    document.querySelectorAll('.extract-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const docId = this.dataset.docId;
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Extracting with AI...';
            
            try {
                const response = await fetch(`/shipping-documents/${docId}/extract`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Show contact matching modal if there are contacts to match
                    if (data.contact_matches && Object.keys(data.contact_matches).length > 0) {
                        showContactMatchingModal(data.contact_matches, docId);
                    } else {
                        // No contacts extracted, just reload
                        location.reload();
                    }
                } else {
                    alert('Extraction failed: ' + (data.error || 'Unknown error'));
                    this.disabled = false;
                    this.innerHTML = originalText;
                }
            } catch (error) {
                alert('Request failed: ' + error.message);
                this.disabled = false;
                this.innerHTML = originalText;
            }
        });
    });
    
    // Show contact matching modal
    function showContactMatchingModal(contactMatches, docId) {
        let html = `
        <div class="modal fade" id="contactMatchingModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Extraction Complete - Review Contacts</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-4">Data has been extracted and shipment details have been auto-populated. Please review and confirm the trade contacts below:</p>
        `;
        
        // Shipper
        if (contactMatches.shipper) {
            html += buildContactSection('shipper', 'Shipper (Exporter)', contactMatches.shipper, docId);
        }
        
        // Consignee
        if (contactMatches.consignee) {
            html += buildContactSection('consignee', 'Consignee (Importer)', contactMatches.consignee, docId);
        }
        
        // Notify Party
        if (contactMatches.notify_party) {
            html += buildContactSection('notify_party', 'Notify Party', contactMatches.notify_party, docId);
        }
        
        html += `
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="location.reload()">
                            Done - Reload Page
                        </button>
                    </div>
                </div>
            </div>
        </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('contactMatchingModal');
        if (existingModal) existingModal.remove();
        
        document.body.insertAdjacentHTML('beforeend', html);
        const modal = new bootstrap.Modal(document.getElementById('contactMatchingModal'));
        modal.show();
        
        // Handle modal close
        document.getElementById('contactMatchingModal').addEventListener('hidden.bs.modal', function() {
            location.reload();
        });
    }
    
    function buildContactSection(type, label, matchData, docId) {
        const extracted = matchData.extracted || {};
        const hasMatch = matchData.matched && matchData.contact;
        const bestMatch = matchData.contact;
        const confidence = matchData.confidence || 0;
        
        let html = `
        <div class="card mb-3 ${hasMatch ? 'border-success' : 'border-warning'}">
            <div class="card-header ${hasMatch ? 'bg-success text-white' : 'bg-warning'}">
                <div class="d-flex justify-content-between align-items-center">
                    <strong>${label}</strong>
                    ${hasMatch 
                        ? `<span class="badge bg-light text-success">Match Found (${Math.round(confidence*100)}%)</span>` 
                        : '<span class="badge bg-dark">No Match - New Contact</span>'}
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">Extracted from Document:</h6>
                        <div class="bg-light p-2 rounded">
                            <strong>${extracted.company_name || 'N/A'}</strong><br>
                            ${extracted.address || ''}${extracted.city ? ', ' + extracted.city : ''}${extracted.country ? ', ' + extracted.country : ''}<br>
                            ${extracted.phone ? '<small>Phone: ' + extracted.phone + '</small>' : ''}
                        </div>
                    </div>
                    <div class="col-md-6">
        `;
        
        if (hasMatch) {
            html += `
                        <h6 class="text-muted">Matched Contact:</h6>
                        <div class="bg-light p-2 rounded">
                            <strong>${bestMatch.company_name}</strong><br>
                            <small class="text-muted">${bestMatch.full_address || 'No address on file'}</small>
                        </div>
                        <button type="button" class="btn btn-success btn-sm mt-2 w-100 link-contact-btn" 
                                data-type="${type}" data-contact-id="${bestMatch.id}">
                            <i class="fas fa-check me-1"></i>Use This Contact
                        </button>
            `;
        } else {
            html += `
                        <h6 class="text-muted">Action Required:</h6>
                        <p class="text-muted small">This contact was not found in your database.</p>
                        <button type="button" class="btn btn-primary btn-sm w-100 add-contact-btn" 
                                data-type="${type}" data-doc-id="${docId}"
                                data-company="${encodeURIComponent(extracted.company_name || '')}"
                                data-address="${encodeURIComponent(extracted.address || '')}"
                                data-city="${encodeURIComponent(extracted.city || '')}"
                                data-country="${encodeURIComponent(extracted.country || '')}"
                                data-phone="${encodeURIComponent(extracted.phone || '')}">
                            <i class="fas fa-plus me-1"></i>Add as New Contact
                        </button>
            `;
        }
        
        html += `
                    </div>
                </div>
            </div>
        </div>
        `;
        
        return html;
    }
    
    // Link existing contact button handler
    document.addEventListener('click', async function(e) {
        if (e.target.closest('.link-contact-btn')) {
            const btn = e.target.closest('.link-contact-btn');
            const type = btn.dataset.type;
            const contactId = btn.dataset.contactId;
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Linking...';
            
            try {
                const response = await fetch(`/shipments/${shipmentId}/link-contact`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ contact_type: type, contact_id: contactId })
                });
                
                const data = await response.json();
                if (data.success) {
                    btn.innerHTML = '<i class="fas fa-check"></i> Linked!';
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-success');
                } else {
                    btn.innerHTML = 'Failed';
                    btn.disabled = false;
                }
            } catch (error) {
                console.error('Link contact error:', error);
                btn.innerHTML = 'Error';
                btn.disabled = false;
            }
        }
        
        // Add new contact button handler
        if (e.target.closest('.add-contact-btn')) {
            const btn = e.target.closest('.add-contact-btn');
            const type = btn.dataset.type;
            const docId = btn.dataset.docId;
            
            const contactData = {
                contact_type: type,
                company_name: decodeURIComponent(btn.dataset.company),
                address: decodeURIComponent(btn.dataset.address),
                city: decodeURIComponent(btn.dataset.city),
                country: decodeURIComponent(btn.dataset.country),
                phone: decodeURIComponent(btn.dataset.phone),
            };
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            
            try {
                const response = await fetch(`/shipping-documents/${docId}/create-contact`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(contactData)
                });
                
                const data = await response.json();
                if (data.success) {
                    btn.innerHTML = '<i class="fas fa-check"></i> Created & Linked!';
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-success');
                } else {
                    alert('Failed to create contact: ' + (data.error || 'Unknown error'));
                    btn.innerHTML = 'Failed';
                    btn.disabled = false;
                }
            } catch (error) {
                console.error('Create contact error:', error);
                btn.innerHTML = 'Error';
                btn.disabled = false;
            }
        }
    });
    
    // Contact selection
    document.querySelectorAll('.contact-select').forEach(select => {
        select.addEventListener('change', async function() {
            const type = this.dataset.type;
            const contactId = this.value;
            
            try {
                const response = await fetch(`/shipments/${shipmentId}/link-contact`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        contact_type: type,
                        contact_id: contactId
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    // Optionally refresh to show updated address
                }
            } catch (error) {
                console.error('Failed to link contact:', error);
            }
        });
    });
    
    // Load available invoices when modal opens
    const addInvoicesModal = document.getElementById('addInvoicesModal');
    if (addInvoicesModal) {
        addInvoicesModal.addEventListener('show.bs.modal', async function() {
            const container = document.getElementById('availableInvoicesContainer');
            
            try {
                const response = await fetch(`/api/shipments/available-invoices?shipment_id=${shipmentId}`);
                const data = await response.json();
                
                if (data.invoices && data.invoices.length > 0) {
                    let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr><th style="width:40px"></th><th>Invoice #</th><th>Date</th><th class="text-end">Amount</th></tr></thead><tbody>';
                    
                    data.invoices.forEach(inv => {
                        html += `<tr>
                            <td><input type="checkbox" name="invoice_ids[]" value="${inv.id}" class="form-check-input"></td>
                            <td>${inv.invoice_number}</td>
                            <td>${inv.invoice_date || 'N/A'}</td>
                            <td class="text-end">$${parseFloat(inv.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<div class="alert alert-info">No available invoices found.</div>';
                }
            } catch (error) {
                container.innerHTML = '<div class="alert alert-danger">Failed to load invoices.</div>';
            }
        });
    }
});
</script>
@endpush
@endsection
