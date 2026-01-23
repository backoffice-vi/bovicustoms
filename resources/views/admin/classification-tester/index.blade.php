@extends('layouts.app')

@section('title', 'Classification Tester - Admin')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <h1 class="h3">
            <i class="fas fa-flask me-2"></i>Classification Tester
        </h1>
        <p class="text-muted">Test the 9-step classification algorithm with verbose output.</p>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Test Classification</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.classification-tester.test') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Country <span class="text-danger">*</span></label>
                            <select name="country_id" class="form-select" required>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}" {{ (isset($country_id) && $country_id == $country->id) ? 'selected' : '' }}>
                                        {{ $country->flag_emoji }} {{ $country->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Item Description <span class="text-danger">*</span></label>
                            <textarea name="item_description" class="form-control" rows="3" required 
                                      placeholder="e.g., Industrial ceramic water pump for chemical processing">{{ $item_description ?? '' }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Classify Item
                        </button>
                    </form>
                </div>
            </div>

            <!-- Sample Items -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">Sample Test Items</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action" onclick="setItem('Laptop computer 15 inch')">Laptop computer 15 inch</a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="setItem('Industrial ceramic water pump')">Industrial ceramic water pump</a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="setItem('Fresh beef cuts refrigerated')">Fresh beef cuts refrigerated</a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="setItem('Steel pipes for construction')">Steel pipes for construction</a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            @if(isset($result))
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Classification Result</h5>
                        @if($result['result']['success'])
                            <span class="badge bg-success">Success</span>
                        @else
                            <span class="badge bg-danger">Failed</span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($result['result']['success'])
                            <!-- Main Result -->
                            <div class="alert alert-success">
                                <h5 class="alert-heading">
                                    <code>{{ $result['result']['code'] }}</code>
                                </h5>
                                <p class="mb-1">{{ $result['result']['description'] }}</p>
                                <hr>
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Duty Rate:</strong> {{ $result['result']['duty_rate'] ?? 'N/A' }}%
                                    </div>
                                    <div class="col-6">
                                        <strong>Confidence:</strong> {{ $result['result']['confidence'] }}%
                                    </div>
                                </div>
                            </div>

                            <!-- Unit of Measurement -->
                            @if($result['result']['unit_of_measurement'])
                                <p><strong>Unit:</strong> {{ $result['result']['unit_of_measurement'] }}</p>
                            @endif

                            <!-- Explanation -->
                            <div class="mb-3">
                                <strong>Explanation:</strong>
                                <p class="text-muted">{{ $result['result']['explanation'] }}</p>
                            </div>

                            <!-- Alternatives -->
                            @if(!empty($result['result']['alternatives']))
                                <div class="mb-3">
                                    <strong>Alternative Codes:</strong>
                                    @foreach($result['result']['alternatives'] as $alt)
                                        <span class="badge bg-secondary me-1">{{ $alt }}</span>
                                    @endforeach
                                </div>
                            @endif

                            <!-- Prohibited/Restricted Status -->
                            @if($result['result']['restricted'])
                                <div class="alert alert-warning">
                                    <strong><i class="fas fa-lock me-1"></i>Restricted Item</strong>
                                    @foreach($result['result']['restricted_items'] as $item)
                                        <div class="mt-2">
                                            <strong>{{ $item['name'] }}</strong>
                                            @if($item['permit_authority'])
                                                - Requires permit from {{ $item['permit_authority'] }}
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <!-- Exemptions -->
                            @if(!empty($result['result']['exemptions_available']))
                                <div class="alert alert-info">
                                    <strong><i class="fas fa-certificate me-1"></i>Available Exemptions:</strong>
                                    @foreach($result['result']['exemptions_available'] as $exemption)
                                        <div class="mt-2">
                                            <strong>{{ $exemption['name'] }}</strong>
                                            @if($exemption['legal_reference'])
                                                <small class="text-muted">({{ $exemption['legal_reference'] }})</small>
                                            @endif
                                            @if(!empty($exemption['conditions']))
                                                <ul class="small mb-0 mt-1">
                                                    @foreach($exemption['conditions'] as $cond)
                                                        <li>{{ $cond['description'] }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <!-- Duty Calculation -->
                            @if($result['result']['duty_calculation'])
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>Duty Calculation (on $100 value)</h6>
                                        <table class="table table-sm mb-0">
                                            <tr>
                                                <td>Base Duty ({{ $result['result']['duty_calculation']['base_duty_rate'] }}%)</td>
                                                <td class="text-end">${{ number_format($result['result']['duty_calculation']['base_duty_amount'], 2) }}</td>
                                            </tr>
                                            @foreach($result['result']['duty_calculation']['additional_levies'] as $levy)
                                                <tr>
                                                    <td>{{ $levy['name'] }} ({{ $levy['rate'] }})</td>
                                                    <td class="text-end">${{ number_format($levy['amount'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                            <tr class="fw-bold">
                                                <td>Total Duty</td>
                                                <td class="text-end">${{ number_format($result['result']['duty_calculation']['total_duty'], 2) }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            @endif

                        @else
                            <div class="alert alert-danger">
                                <strong>Classification Failed</strong>
                                <p class="mb-0">{{ $result['result']['error'] ?? 'Unknown error' }}</p>
                            </div>

                            @if(isset($result['result']['prohibited']) && $result['result']['prohibited'])
                                <div class="alert alert-danger">
                                    <strong><i class="fas fa-ban me-1"></i>Item is PROHIBITED</strong>
                                    @foreach($result['result']['prohibited_items'] as $item)
                                        <div class="mt-2">{{ $item['name'] }}: {{ $item['description'] }}</div>
                                    @endforeach
                                </div>
                            @endif
                        @endif

                        <!-- Classification Path -->
                        @if(!empty($result['result']['classification_path']))
                            <div class="mt-4">
                                <h6>Classification Path:</h6>
                                <ol class="small">
                                    @foreach($result['result']['classification_path'] as $step)
                                        <li>{{ $step }}</li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body text-center py-5 text-muted">
                        <i class="fas fa-search fa-3x mb-3"></i>
                        <p>Enter an item description and click "Classify Item" to test the classification algorithm.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    function setItem(text) {
        document.querySelector('textarea[name="item_description"]').value = text;
        event.preventDefault();
    }
</script>
@endpush
@endsection
