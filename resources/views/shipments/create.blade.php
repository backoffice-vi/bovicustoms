@extends('layouts.app')

@section('title', 'Create Shipment - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('shipments.index') }}">Shipments</a></li>
                <li class="breadcrumb-item active">Create New</li>
            </ol>
        </nav>
        <h2 class="mb-1"><i class="fas fa-ship me-2"></i>Create New Shipment</h2>
        <p class="text-muted mb-0">Group invoices together and prepare for shipping documents</p>
    </div>

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <strong>Please fix the following errors:</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <form action="{{ route('shipments.store') }}" method="POST">
        @csrf

        <div class="row">
            {{-- Left Column - Invoice Selection --}}
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Select Invoices</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Select one or more invoices to include in this shipment. You can combine multiple invoices that ship together.
                        </p>
                        
                        @if($availableInvoices->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th>Invoice #</th>
                                        <th>Date</th>
                                        <th>Country</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($availableInvoices as $invoice)
                                    <tr>
                                        <td>
                                            <input type="checkbox" 
                                                   name="invoice_ids[]" 
                                                   value="{{ $invoice->id }}" 
                                                   class="form-check-input invoice-checkbox"
                                                   data-amount="{{ $invoice->total_amount }}"
                                                   {{ in_array($invoice->id, old('invoice_ids', [])) ? 'checked' : '' }}>
                                        </td>
                                        <td class="fw-semibold">{{ $invoice->invoice_number }}</td>
                                        <td>{{ $invoice->invoice_date?->format('M d, Y') ?? 'N/A' }}</td>
                                        <td>
                                            @if($invoice->country)
                                                <i class="fas fa-flag me-1 text-muted"></i>{{ $invoice->country->name }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-end">${{ number_format($invoice->total_amount, 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold">Selected Total (FOB):</td>
                                        <td class="text-end fw-bold" id="selectedTotal">$0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No processed invoices available. 
                            <a href="{{ route('invoices.create') }}">Upload an invoice first</a>.
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Right Column - Shipment Details --}}
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Shipment Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="country_id" class="form-label">Destination Country <span class="text-danger">*</span></label>
                            <select name="country_id" id="country_id" class="form-select @error('country_id') is-invalid @enderror" required>
                                <option value="">Select Country...</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}" {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                        {{ $country->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('country_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info small mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Shipper & Consignee:</strong> These will be auto-detected when you upload a Bill of Lading after creating the shipment.
                        </div>

                        <div class="mb-3">
                            <label for="insurance_method" class="form-label">Insurance Calculation</label>
                            <select name="insurance_method" id="insurance_method" class="form-select">
                                <option value="percentage" {{ old('insurance_method', 'percentage') == 'percentage' ? 'selected' : '' }}>Percentage of FOB</option>
                                <option value="manual" {{ old('insurance_method') == 'manual' ? 'selected' : '' }}>Manual Entry</option>
                                <option value="document" {{ old('insurance_method') == 'document' ? 'selected' : '' }}>From Insurance Certificate</option>
                            </select>
                            <small class="text-muted" id="insuranceDefaultNote"></small>
                        </div>

                        <div class="mb-3" id="insurancePercentageGroup">
                            <label for="insurance_percentage" class="form-label">Insurance Rate (%)</label>
                            <input type="number" step="0.01" name="insurance_percentage" id="insurance_percentage" 
                                   class="form-control" value="{{ old('insurance_percentage', '1.00') }}" 
                                   min="0" max="100">
                            <small class="text-muted">Common rates: 0.5% - 1.5% of FOB</small>
                        </div>

                        <div class="mb-3" id="insuranceManualGroup" style="display: none;">
                            <label for="insurance_total" class="form-label">Insurance Amount ($)</label>
                            <input type="number" step="0.01" name="insurance_total" id="insurance_total" 
                                   class="form-control" value="{{ old('insurance_total', '0') }}" min="0">
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes (optional)</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100" {{ $availableInvoices->isEmpty() ? 'disabled' : '' }}>
                    <i class="fas fa-save me-2"></i>Create Shipment
                </button>
                <p class="text-muted text-center mt-2">
                    <small>You can upload shipping documents after creating the shipment.</small>
                </p>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    const selectedTotal = document.getElementById('selectedTotal');
    const insuranceMethod = document.getElementById('insurance_method');
    const insurancePercentage = document.getElementById('insurance_percentage');
    const insurancePercentageGroup = document.getElementById('insurancePercentageGroup');
    const insuranceManualGroup = document.getElementById('insuranceManualGroup');
    const insuranceDefaultNote = document.getElementById('insuranceDefaultNote');
    const countrySelect = document.getElementById('country_id');

    // Country insurance defaults
    const countryDefaults = {
        @foreach($countries as $country)
        {{ $country->id }}: {
            method: '{{ $country->default_insurance_method ?? "percentage" }}',
            percentage: {{ $country->default_insurance_percentage ?? 1.00 }}
        },
        @endforeach
    };

    // Calculate selected total
    function updateTotal() {
        let total = 0;
        checkboxes.forEach(cb => {
            if (cb.checked) {
                total += parseFloat(cb.dataset.amount) || 0;
            }
        });
        selectedTotal.textContent = '$' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Select all toggle
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateTotal();
        });
    }

    // Individual checkbox changes
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            updateTotal();
            // Update select all state
            if (selectAll) {
                selectAll.checked = [...checkboxes].every(c => c.checked);
            }
        });
    });

    // Insurance method toggle
    function updateInsuranceFields() {
        const method = insuranceMethod.value;
        insurancePercentageGroup.style.display = method === 'percentage' ? 'block' : 'none';
        insuranceManualGroup.style.display = method === 'manual' ? 'block' : 'none';
    }

    // Country selection - update insurance defaults
    countrySelect.addEventListener('change', function() {
        const countryId = this.value;
        if (countryId && countryDefaults[countryId]) {
            const defaults = countryDefaults[countryId];
            insuranceMethod.value = defaults.method;
            insurancePercentage.value = defaults.percentage.toFixed(2);
            insuranceDefaultNote.textContent = `Default for this country: ${defaults.percentage}% of FOB`;
            updateInsuranceFields();
        } else {
            insuranceDefaultNote.textContent = '';
        }
    });

    insuranceMethod.addEventListener('change', updateInsuranceFields);
    updateInsuranceFields();
    updateTotal();

    // Trigger country change if pre-selected
    if (countrySelect.value) {
        countrySelect.dispatchEvent(new Event('change'));
    }
});
</script>
@endpush
@endsection
