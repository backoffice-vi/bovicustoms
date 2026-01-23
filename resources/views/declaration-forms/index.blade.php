@extends('layouts.app')

@section('title', 'Declaration Forms - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-alt me-2"></i>Declaration Forms</h2>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Upload Invoice
        </a>
    </div>

    @if($declarationForms->count() > 0)
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Form Number</th>
                                <th>Country</th>
                                <th>Declaration Date</th>
                                <th>Total Duty</th>
                                <th>Invoice</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($declarationForms as $form)
                            <tr>
                                <td>
                                    <strong>{{ $form->form_number ?? 'N/A' }}</strong>
                                </td>
                                <td>
                                    @if($form->country)
                                        <i class="fas fa-flag me-1"></i>{{ $form->country->name }}
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>{{ $form->declaration_date ? $form->declaration_date->format('M d, Y') : 'N/A' }}</td>
                                <td>${{ number_format($form->total_duty, 2) }}</td>
                                <td>
                                    @if($form->invoice)
                                        <a href="{{ route('invoices.show', $form->invoice_id) }}" class="text-decoration-none">
                                            Invoice #{{ $form->invoice_id }}
                                        </a>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td>{{ $form->created_at->format('M d, Y') }}</td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('declaration-forms.show', $form) }}" class="btn btn-sm btn-outline-primary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-success" title="Download" disabled>
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" title="Print" disabled>
                                            <i class="fas fa-print"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="mt-3">
            {{ $declarationForms->links() }}
        </div>
    @else
        <!-- Empty state -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                <h4>No Declaration Forms Yet</h4>
                <p class="text-muted mb-4">
                    Declaration forms are automatically generated when you process invoices with customs codes assigned.
                </p>
                <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-upload me-2"></i>Upload Your First Invoice
                </a>
            </div>
        </div>

        <!-- Quick Info Cards -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-upload text-primary me-2"></i>Step 1: Upload
                        </h5>
                        <p class="card-text">Upload your commercial invoice in PDF, JPG, or PNG format.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-robot text-success me-2"></i>Step 2: AI Processing
                        </h5>
                        <p class="card-text">Our AI extracts data and assigns appropriate customs codes.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-file-download text-info me-2"></i>Step 3: Generate
                        </h5>
                        <p class="card-text">Download your completed customs declaration form.</p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
