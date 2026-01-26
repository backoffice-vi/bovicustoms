@extends('layouts.app')

@section('title', 'Declaration Details - Agent Portal')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-file-alt me-2"></i>Declaration Details</h2>
            <p class="text-muted mb-0">{{ $declaration->form_number }}</p>
        </div>
        <div class="btn-group">
            @if($declaration->canBeSubmitted())
                <a href="{{ route('agent.declarations.submit-form', $declaration) }}" class="btn btn-success">
                    <i class="fas fa-paper-plane me-2"></i>Submit to Customs
                </a>
            @endif
            <a href="{{ route('agent.declarations.index') }}" class="btn btn-outline-secondary">
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
            <!-- Declaration Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-info-circle me-2"></i>Declaration Information</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl>
                                <dt>Form Number</dt>
                                <dd><code>{{ $declaration->form_number }}</code></dd>
                                
                                <dt>Client</dt>
                                <dd>
                                    <a href="{{ route('agent.clients.show', $declaration->organization) }}">
                                        {{ $declaration->organization->name ?? 'N/A' }}
                                    </a>
                                </dd>
                                
                                <dt>Country</dt>
                                <dd>{{ $declaration->country->name ?? 'N/A' }}</dd>
                                
                                <dt>Declaration Date</dt>
                                <dd>{{ $declaration->declaration_date?->format('M d, Y') ?? 'N/A' }}</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl>
                                <dt>B/L Number</dt>
                                <dd>{{ $declaration->bill_of_lading_number ?? 'N/A' }}</dd>
                                
                                <dt>Manifest Number</dt>
                                <dd>{{ $declaration->manifest_number ?? 'N/A' }}</dd>
                                
                                <dt>Carrier</dt>
                                <dd>{{ $declaration->carrier_name ?? 'N/A' }}</dd>
                                
                                <dt>Port of Arrival</dt>
                                <dd>{{ $declaration->port_of_arrival ?? 'N/A' }}</dd>
                            </dl>
                        </div>
                    </div>

                    @if($declaration->shipperContact || $declaration->consigneeContact)
                    <hr>
                    <div class="row">
                        @if($declaration->shipperContact)
                        <div class="col-md-6">
                            <h6 class="text-muted">Shipper</h6>
                            <p class="mb-0">
                                <strong>{{ $declaration->shipperContact->company_name }}</strong><br>
                                <small class="text-muted">{{ $declaration->shipperContact->full_address }}</small>
                            </p>
                        </div>
                        @endif
                        @if($declaration->consigneeContact)
                        <div class="col-md-6">
                            <h6 class="text-muted">Consignee</h6>
                            <p class="mb-0">
                                <strong>{{ $declaration->consigneeContact->company_name }}</strong><br>
                                <small class="text-muted">{{ $declaration->consigneeContact->full_address }}</small>
                            </p>
                        </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>

            <!-- Value Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-calculator me-2"></i>Value & Duty Summary</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <td>FOB Value</td>
                                    <td class="text-end">${{ number_format($declaration->fob_value ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Freight</td>
                                    <td class="text-end">${{ number_format($declaration->freight_total ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Insurance</td>
                                    <td class="text-end">${{ number_format($declaration->insurance_total ?? 0, 2) }}</td>
                                </tr>
                                <tr class="table-primary fw-bold">
                                    <td>CIF Value</td>
                                    <td class="text-end">${{ number_format($declaration->cif_value ?? 0, 2) }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <td>Customs Duty</td>
                                    <td class="text-end">${{ number_format($declaration->customs_duty_total ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Wharfage</td>
                                    <td class="text-end">${{ number_format($declaration->wharfage_total ?? 0, 2) }}</td>
                                </tr>
                                @if($declaration->other_levies_total > 0)
                                <tr>
                                    <td>Other Levies</td>
                                    <td class="text-end">${{ number_format($declaration->other_levies_total ?? 0, 2) }}</td>
                                </tr>
                                @endif
                                <tr class="table-success fw-bold">
                                    <td>Total Payable</td>
                                    <td class="text-end">${{ number_format($declaration->total_duty ?? 0, 2) }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            @if($declaration->declarationItems->count() > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-list me-2"></i>Line Items ({{ $declaration->declarationItems->count() }})</strong>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Description</th>
                                    <th>HS Code</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($declaration->declarationItems as $item)
                                <tr>
                                    <td>{{ $item->line_number }}</td>
                                    <td>{{ Str::limit($item->description, 50) }}</td>
                                    <td><code>{{ $item->hs_code ?? 'N/A' }}</code></td>
                                    <td class="text-end">{{ $item->quantity ?? '-' }}</td>
                                    <td class="text-end">${{ number_format($item->line_total ?? 0, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-4">
            <!-- Submission Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-paper-plane me-2"></i>Submission Status</strong>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <span class="badge bg-{{ $declaration->submission_status_color }} fs-5 px-4 py-2">
                            {{ $declaration->submission_status_label }}
                        </span>
                    </div>
                    
                    @if($declaration->submitted_at)
                    <dl class="mb-0">
                        <dt>Submitted At</dt>
                        <dd>{{ $declaration->submitted_at->format('M d, Y H:i') }}</dd>
                        
                        @if($declaration->submittedBy)
                        <dt>Submitted By</dt>
                        <dd>{{ $declaration->submittedBy->name }}</dd>
                        @endif
                        
                        @if($declaration->submission_reference)
                        <dt>Reference Number</dt>
                        <dd><code>{{ $declaration->submission_reference }}</code></dd>
                        @endif
                        
                        @if($declaration->submission_notes)
                        <dt>Notes</dt>
                        <dd>{{ $declaration->submission_notes }}</dd>
                        @endif
                    </dl>
                    @endif

                    @if($declaration->canBeSubmitted())
                    <hr>
                    <a href="{{ route('agent.declarations.submit-form', $declaration) }}" class="btn btn-success w-100">
                        <i class="fas fa-paper-plane me-2"></i>Submit to Customs
                    </a>
                    @endif

                    @if($declaration->submission_status === 'draft')
                    <form action="{{ route('agent.declarations.mark-ready', $declaration) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary w-100 mt-2">
                            <i class="fas fa-check me-2"></i>Mark as Ready
                        </button>
                    </form>
                    @endif
                </div>
            </div>

            <!-- Attachments -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-paperclip me-2"></i>Attachments</strong>
                    <span class="badge bg-secondary">{{ $declaration->submissionAttachments->count() }}</span>
                </div>
                <div class="card-body">
                    @if($declaration->submissionAttachments->count() > 0)
                        <ul class="list-group list-group-flush">
                            @foreach($declaration->submissionAttachments as $attachment)
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <i class="{{ $attachment->file_icon }} me-2"></i>
                                        <span>{{ Str::limit($attachment->file_name, 25) }}</span>
                                        <br>
                                        <small class="text-muted">
                                            {{ $attachment->document_type_label }} - {{ $attachment->formatted_file_size }}
                                        </small>
                                    </div>
                                    <a href="{{ route('agent.attachments.download', $attachment) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted text-center mb-0">No attachments uploaded yet.</p>
                    @endif
                    
                    @if($declaration->canBeSubmitted())
                    <hr>
                    <a href="{{ route('agent.declarations.submit-form', $declaration) }}" class="btn btn-outline-primary btn-sm w-100">
                        <i class="fas fa-upload me-2"></i>Upload Documents
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
