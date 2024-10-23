@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Review Extracted Invoice Data</h2>
    <form action="{{ route('invoices.confirm') }}" method="POST">
        @csrf
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($extractedData['items'] as $index => $item)
                    <tr>
                        <td>
                            <input type="text" class="form-control" name="items[{{ $index }}][description]" value="{{ $item['description'] }}" required>
                        </td>
                        <td>
                            <input type="number" class="form-control quantity" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] }}" required min="1" step="1">
                        </td>
                        <td>
                            <input type="number" class="form-control unit-price" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] }}" required min="0" step="0.01">
                        </td>
                        <td>
                            <input type="number" class="form-control total-price" value="{{ $item['quantity'] * $item['unit_price'] }}" readonly>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary btn-lg">Confirm and Proceed</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const quantity = row.querySelector('.quantity');
            const unitPrice = row.querySelector('.unit-price');
            const totalPrice = row.querySelector('.total-price');

            [quantity, unitPrice].forEach(input => {
                input.addEventListener('input', updateTotalPrice);
            });

            function updateTotalPrice() {
                totalPrice.value = (parseFloat(quantity.value) * parseFloat(unitPrice.value)).toFixed(2);
            }
        });
    });
</script>
@endpush
