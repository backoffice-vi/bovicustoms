@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Assign Customs Codes</h2>
    <form action="{{ route('invoices.finalize') }}" method="POST">
        @csrf
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total Price</th>
                        <th>Recommended Customs Code</th>
                        <th>Previously Used Code</th>
                        <th>Assigned Customs Code</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($itemsWithCodes as $index => $item)
                    <tr>
                        <td>{{ $item['description'] }}</td>
                        <td>{{ $item['quantity'] }}</td>
                        <td>{{ $item['unit_price'] }}</td>
                        <td>{{ $item['quantity'] * $item['unit_price'] }}</td>
                        <td>
                            {{ $item['recommended_code'] }}
                            <span class="badge bg-info">{{ $item['confidence'] }}%</span>
                        </td>
                        <td>{{ $item['previously_used_code'] ?? 'N/A' }}</td>
                        <td>
                            <input type="hidden" name="items[{{ $index }}][description]" value="{{ $item['description'] }}">
                            <input type="hidden" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] }}">
                            <input type="hidden" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] }}">
                            <select class="form-select" name="items[{{ $index }}][customs_code]" required>
                                <option value="">Select a code</option>
                                <option value="{{ $item['recommended_code'] }}" {{ $item['recommended_code'] == $item['previously_used_code'] ? 'selected' : '' }}>
                                    {{ $item['recommended_code'] }}
                                </option>
                                @if($item['previously_used_code'] && $item['previously_used_code'] != $item['recommended_code'])
                                <option value="{{ $item['previously_used_code'] }}" selected>
                                    {{ $item['previously_used_code'] }}
                                </option>
                                @endif
                                <!-- Add more options here based on your customs codes database -->
                            </select>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="text-center mt-4">
            <button type="submit" class="btn btn-success btn-lg">Save and Generate Declaration Form</button>
        </div>
    </form>
</div>
@endsection
