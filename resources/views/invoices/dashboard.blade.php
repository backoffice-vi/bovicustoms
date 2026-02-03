@extends('layouts.app')

@section('content')
<div class="container py-4">
    {{-- Welcome Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-1 fw-bold text-dark">Welcome back, {{ auth()->user()->name }}</h1>
            <p class="text-muted mb-0">
                @if($organization)
                    <i class="fas fa-building me-1"></i> {{ $organization->name }}
                    <span class="badge bg-{{ $organization->subscription_plan === 'enterprise' ? 'primary' : ($organization->subscription_plan === 'pro' ? 'info' : 'secondary') }} ms-2">
                        {{ ucfirst($organization->subscription_plan) }}
                    </span>
                    @if($organization->isOnTrial())
                        <span class="badge bg-warning text-dark ms-1">
                            <i class="fas fa-clock me-1"></i>Trial ends {{ $organization->trial_ends_at->diffForHumans() }}
                        </span>
                    @endif
                @endif
            </p>
        </div>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-lg shadow-sm">
            <i class="fas fa-plus me-2"></i>Upload Invoice
        </a>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #f59e0b !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                                <i class="fas fa-hourglass-half text-warning fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h3 class="mb-0 fw-bold">{{ $pendingInvoices->count() }}</h3>
                            <p class="text-muted mb-0 small">Pending Invoices</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #10b981 !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3">
                                <i class="fas fa-check-circle text-success fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h3 class="mb-0 fw-bold">{{ $processedInvoices->count() }}</h3>
                            <p class="text-muted mb-0 small">Processed Invoices</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #6366f1 !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                                <i class="fas fa-file-alt text-primary fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h3 class="mb-0 fw-bold">{{ $recentForms->count() }}</h3>
                            <p class="text-muted mb-0 small">Declaration Forms</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #8b5cf6 !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-purple bg-opacity-10 p-3" style="background-color: rgba(139, 92, 246, 0.1);">
                                <i class="fas fa-chart-line fa-lg" style="color: #8b5cf6;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h3 class="mb-0 fw-bold">{{ $invoicesThisMonth }}/{{ $invoiceLimit }}</h3>
                            <p class="text-muted mb-0 small">This Month</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Pending Invoices --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 fw-semibold">
                            <i class="fas fa-hourglass-half text-warning me-2"></i>Pending Invoices
                        </h5>
                        <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                </div>
                <div class="card-body">
                    @if($pendingInvoices->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($pendingInvoices as $invoice)
                                <a href="{{ route('invoices.show', $invoice) }}" class="list-group-item list-group-item-action border-0 rounded mb-2 bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1 fw-semibold">{{ $invoice->invoice_number ?? 'Invoice #' . $invoice->id }}</h6>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>{{ $invoice->created_at->format('M d, Y') }}
                                                @if($invoice->total_amount)
                                                    <span class="ms-2"><i class="fas fa-dollar-sign me-1"></i>{{ number_format($invoice->total_amount, 2) }}</span>
                                                @endif
                                            </small>
                                        </div>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-inbox fa-3x text-muted"></i>
                            </div>
                            <h6 class="text-muted">No pending invoices</h6>
                            <p class="text-muted small mb-3">Upload your first invoice to get started</p>
                            <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-upload me-1"></i>Upload Invoice
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Processed Invoices --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 fw-semibold">
                            <i class="fas fa-check-circle text-success me-2"></i>Processed Invoices
                        </h5>
                        <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                </div>
                <div class="card-body">
                    @if($processedInvoices->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach($processedInvoices as $invoice)
                                <a href="{{ route('invoices.show', $invoice) }}" class="list-group-item list-group-item-action border-0 rounded mb-2 bg-light">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1 fw-semibold">{{ $invoice->invoice_number ?? 'Invoice #' . $invoice->id }}</h6>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>{{ $invoice->created_at->format('M d, Y') }}
                                                @if($invoice->total_amount)
                                                    <span class="ms-2"><i class="fas fa-dollar-sign me-1"></i>{{ number_format($invoice->total_amount, 2) }}</span>
                                                @endif
                                            </small>
                                        </div>
                                        <span class="badge bg-success">Processed</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-clipboard-check fa-3x text-muted"></i>
                            </div>
                            <h6 class="text-muted">No processed invoices yet</h6>
                            <p class="text-muted small">Invoices will appear here after processing</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Declaration Forms --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0 fw-semibold">
                            <i class="fas fa-file-alt text-primary me-2"></i>Recent Declaration Forms
                        </h5>
                        <a href="{{ route('declaration-forms.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
                    </div>
                </div>
                <div class="card-body">
                    @if($recentForms->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Form Number</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentForms as $form)
                                        <tr>
                                            <td>
                                                <span class="fw-semibold">{{ $form->form_number ?? 'Form #' . $form->id }}</span>
                                            </td>
                                            <td>
                                                <i class="fas fa-calendar text-muted me-1"></i>
                                                {{ $form->declaration_date ? $form->declaration_date->format('M d, Y') : $form->created_at->format('M d, Y') }}
                                            </td>
                                            <td>
                                                <span class="badge bg-info">{{ ucfirst($form->status ?? 'Draft') }}</span>
                                            </td>
                                            <td class="text-end">
                                                <a href="{{ route('declaration-forms.show', $form) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-folder-open fa-3x text-muted"></i>
                            </div>
                            <h6 class="text-muted">No declaration forms yet</h6>
                            <p class="text-muted small">Declaration forms are generated from processed invoices</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 bg-gradient text-white shadow" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body py-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="fw-bold mb-2">Ready to process your next shipment?</h5>
                            <p class="mb-0 opacity-75">Upload an invoice to automatically classify items and generate customs declarations.</p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <a href="{{ route('invoices.create') }}" class="btn btn-light btn-lg shadow-sm">
                                <i class="fas fa-upload me-2"></i>Upload Invoice
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
