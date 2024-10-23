@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">Dashboard</h1>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    Pending Invoices
                </div>
                <div class="card-body">
                    @if($pendingInvoices->count() > 0)
                        <ul class="list-group">
                            @foreach($pendingInvoices as $invoice)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>{{ $invoice->invoice_number }}</span>
                                    <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-primary">View</a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p>No pending invoices.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    Processed Invoices
                </div>
                <div class="card-body">
                    @if($processedInvoices->count() > 0)
                        <ul class="list-group">
                            @foreach($processedInvoices as $invoice)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>{{ $invoice->invoice_number }}</span>
                                    <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-info">View</a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p>No processed invoices.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    Recent Declaration Forms
                </div>
                <div class="card-body">
                    @if($recentForms->count() > 0)
                        <ul class="list-group">
                            @foreach($recentForms as $form)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>{{ $form->form_number }}</span>
                                    <div>
                                        <span class="badge bg-secondary me-2">{{ $form->declaration_date->format('Y-m-d') }}</span>
                                        <a href="{{ route('declaration-forms.show', $form) }}" class="btn btn-sm btn-success">View</a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p>No recent declaration forms.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <a href="{{ route('invoices.create') }}" class="btn btn-primary">Upload New Invoice</a>
    </div>
</div>
@endsection
