@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Invoice Details</h2>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Invoice Number: {{ $invoice->invoice_number }}</h5>
            <p class="card-text">Date: {{ $invoice->invoice_date }}</p>
            <p class="card-text">Total Amount: ${{ number_format($invoice->total_amount, 2) }}</p>
            <p class="card-text">Status: {{ ucfirst($invoice->status) }}</p>
        </div>
    </div>

    <h3 class="mt-4">Items</h3>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total Price</th>
                    <th>Customs Code</th>
                </tr>
            </thead>
            <tbody>
                @foreach(json_decode($invoice->items, true) as $item)
                <tr>
                    <td>{{ $item['description'] }}</td>
                    <td>{{ $item['quantity'] }}</td>
                    <td>${{ number_format($item['unit_price'], 2) }}</td>
                    <td>${{ number_format($item['quantity'] * $item['unit_price'], 2) }}</td>
                    <td>{{ $item['customs_code'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">Back to Dashboard</a>
        <!-- Add more action buttons here if needed -->
    </div>
</div>
@endsection
