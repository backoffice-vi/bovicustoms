@extends('layouts.app')

@section('title', 'Shipments - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-ship me-2"></i>Shipments</h2>
            <p class="text-muted mb-0">Manage shipments, combine invoices, and generate declarations</p>
        </div>
        <a href="{{ route('shipments.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>New Shipment
        </a>
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

    @if($shipments->count() > 0)
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Document #</th>
                            <th>Status</th>
                            <th>Invoices</th>
                            <th>Country</th>
                            <th>Consignee</th>
                            <th class="text-end">FOB Value</th>
                            <th class="text-end">CIF Value</th>
                            <th>Created</th>
                            <th style="width: 120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shipments as $shipment)
                        <tr>
                            <td>
                                <a href="{{ route('shipments.show', $shipment) }}" class="fw-semibold text-decoration-none">
                                    {{ $shipment->primary_document_number ?? 'SHIP-'.$shipment->id }}
                                </a>
                                <br>
                                <small class="text-muted">
                                    @if($shipment->bill_of_lading_number)
                                        B/L: {{ $shipment->bill_of_lading_number }}
                                    @elseif($shipment->awb_number)
                                        AWB: {{ $shipment->awb_number }}
                                    @endif
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-{{ $shipment->status_color }}">
                                    {{ $shipment->status_label }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-secondary">{{ $shipment->invoices_count }} invoice(s)</span>
                                @if($shipment->shipping_documents_count > 0)
                                    <span class="badge bg-info">{{ $shipment->shipping_documents_count }} doc(s)</span>
                                @endif
                            </td>
                            <td>
                                @if($shipment->country)
                                    <i class="fas fa-flag me-1 text-muted"></i>{{ $shipment->country->name }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($shipment->consigneeContact)
                                    {{ Str::limit($shipment->consigneeContact->company_name, 20) }}
                                @else
                                    <span class="text-muted">Not set</span>
                                @endif
                            </td>
                            <td class="text-end">${{ number_format($shipment->fob_total, 2) }}</td>
                            <td class="text-end fw-semibold">${{ number_format($shipment->cif_total, 2) }}</td>
                            <td>{{ $shipment->created_at->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('shipments.show', $shipment) }}" class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if($shipment->canGenerateDeclaration())
                                <form action="{{ route('shipments.generate-declaration', $shipment) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Generate Declaration">
                                        <i class="fas fa-file-alt"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Pagination --}}
    @if($shipments->hasPages())
    <div class="mt-4">
        {{ $shipments->links() }}
    </div>
    @endif
    @else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-ship fa-4x text-muted mb-3"></i>
            <h4>No Shipments Yet</h4>
            <p class="text-muted mb-4">Create a shipment to combine invoices and upload shipping documents like Bill of Lading.</p>
            <a href="{{ route('shipments.create') }}" class="btn btn-primary btn-lg">
                <i class="fas fa-plus me-2"></i>Create Your First Shipment
            </a>
        </div>
    </div>
    @endif
</div>
@endsection
