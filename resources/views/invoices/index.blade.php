@extends('layouts.app')

@section('title', 'Invoices - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-file-invoice me-2"></i>Invoices</h2>
            <p class="text-muted mb-0">View and manage your processed invoices</p>
        </div>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Upload Invoice
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if($invoices->count() > 0)
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Invoice Number</th>
                            <th>Date</th>
                            <th>Country</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th style="width: 100px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                        <tr>
                            <td>
                                <a href="{{ route('invoices.show', $invoice) }}" class="fw-semibold text-decoration-none">
                                    {{ $invoice->invoice_number }}
                                </a>
                            </td>
                            <td>{{ $invoice->invoice_date?->format('M d, Y') ?? 'N/A' }}</td>
                            <td>
                                @if($invoice->country)
                                    <i class="fas fa-flag me-1 text-muted"></i>{{ $invoice->country->name }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-end fw-semibold">${{ number_format($invoice->total_amount, 2) }}</td>
                            <td>
                                @php
                                    $statusClass = match($invoice->status) {
                                        'processed' => 'success',
                                        'pending' => 'warning',
                                        'draft' => 'secondary',
                                        default => 'primary'
                                    };
                                @endphp
                                <span class="badge bg-{{ $statusClass }}">{{ ucfirst($invoice->status) }}</span>
                            </td>
                            <td>{{ $invoice->created_at->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Pagination --}}
    @if($invoices->hasPages())
    <div class="mt-4">
        {{ $invoices->links() }}
    </div>
    @endif
    @else
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-file-invoice fa-4x text-muted mb-3"></i>
            <h4>No Invoices Yet</h4>
            <p class="text-muted mb-4">Upload your first invoice to get started with customs classification.</p>
            <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-lg">
                <i class="fas fa-upload me-2"></i>Upload Your First Invoice
            </a>
        </div>
    </div>
    @endif
</div>
@endsection
