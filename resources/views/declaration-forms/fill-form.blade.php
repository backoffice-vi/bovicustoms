@extends('layouts.app')

@section('title', 'Review Form Data - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-check-circle me-2"></i>Review: {{ $filledForm->template?->name }}</h2>
            <p class="text-muted mb-0">
                Declaration {{ $declarationForm->form_number }} | 
                <span class="text-success">
                    <i class="fas fa-magic me-1"></i>{{ $mappingResult['auto_filled_count'] ?? 0 }} of {{ $mappingResult['total_fields'] ?? 0 }} fields auto-filled
                </span>
            </p>
        </div>
        <a href="{{ route('declaration-forms.select-templates', $declarationForm) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Auto-fill success banner -->
    @if(($mappingResult['auto_filled_count'] ?? 0) > 0)
    <div class="alert alert-success d-flex align-items-center mb-4">
        <i class="fas fa-robot fa-2x me-3"></i>
        <div>
            <strong>Data Auto-Filled!</strong>
            <p class="mb-0 small">
                We've automatically populated {{ $mappingResult['auto_filled_count'] }} fields from your invoice, shipment, and trade contacts.
                Review the data below and make any changes if needed.
            </p>
        </div>
    </div>
    @endif

    <form action="{{ route('declaration-forms.process-fill', [$declarationForm, $filledForm]) }}" method="POST" id="fillForm">
        @csrf
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Trade Contacts Section -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <strong><i class="fas fa-users me-2"></i>Trade Contacts</strong>
                            <small class="text-muted d-block">Auto-selected from your declaration. Change if needed.</small>
                        </div>
                        <span class="badge bg-success"><i class="fas fa-check me-1"></i>Auto-selected</span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @if($shippers->count() > 0)
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-shipping-fast text-info me-1"></i>Shipper / Exporter
                                </label>
                                <select name="shipper_contact_id" class="form-select contact-select" data-type="shipper">
                                    <option value="">-- Select Shipper --</option>
                                    @foreach($shippers as $contact)
                                        <option value="{{ $contact->id }}" {{ ($selectedShipperId ?? null) == $contact->id ? 'selected' : '' }}>
                                            {{ $contact->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <a href="{{ route('trade-contacts.create', ['type' => 'shipper']) }}" 
                                   class="btn btn-link btn-sm p-0 mt-1" target="_blank">
                                    <i class="fas fa-plus me-1"></i>Add new shipper
                                </a>
                            </div>
                            @endif

                            @if($consignees->count() > 0)
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-warehouse text-success me-1"></i>Consignee / Importer
                                </label>
                                <select name="consignee_contact_id" class="form-select contact-select" data-type="consignee">
                                    <option value="">-- Select Consignee --</option>
                                    @foreach($consignees as $contact)
                                        <option value="{{ $contact->id }}" {{ ($selectedConsigneeId ?? null) == $contact->id ? 'selected' : '' }}>
                                            {{ $contact->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <a href="{{ route('trade-contacts.create', ['type' => 'consignee']) }}" 
                                   class="btn btn-link btn-sm p-0 mt-1" target="_blank">
                                    <i class="fas fa-plus me-1"></i>Add new consignee
                                </a>
                            </div>
                            @endif

                            @if($notifyParties->count() > 0)
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-bell text-warning me-1"></i>Notify Party
                                </label>
                                <select name="notify_party_contact_id" class="form-select contact-select" data-type="notify_party">
                                    <option value="">-- Select Notify Party --</option>
                                    @foreach($notifyParties as $contact)
                                        <option value="{{ $contact->id }}" {{ ($selectedNotifyPartyId ?? null) == $contact->id ? 'selected' : '' }}>
                                            {{ $contact->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif

                            @if($brokers->count() > 0)
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-user-tie text-secondary me-1"></i>Customs Broker
                                </label>
                                <select name="broker_contact_id" class="form-select contact-select" data-type="broker">
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $contact)
                                        <option value="{{ $contact->id }}" {{ ($selectedBrokerId ?? null) == $contact->id ? 'selected' : '' }}>
                                            {{ $contact->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif

                            @if($banks->count() > 0)
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-university text-primary me-1"></i>Bank
                                </label>
                                <select name="bank_contact_id" class="form-select contact-select" data-type="bank">
                                    <option value="">-- Select Bank --</option>
                                    @foreach($banks as $contact)
                                        <option value="{{ $contact->id }}" {{ $contact->is_default ? 'selected' : '' }}>
                                            {{ $contact->display_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Auto-Filled Data Review -->
                <div class="card mb-4">
                    <div class="card-header">
                        <strong><i class="fas fa-clipboard-check me-2"></i>Review Form Data</strong>
                        <small class="text-muted d-block">
                            <i class="fas fa-check-circle text-success me-1"></i>Green fields are auto-filled. 
                            <i class="fas fa-edit text-warning me-1"></i>Yellow fields need your input.
                        </small>
                    </div>
                    <div class="card-body">
                        @if(empty($fieldsBySection))
                            <p class="text-muted">No fields detected in this form.</p>
                        @else
                            @foreach($fieldsBySection as $sectionName => $fields)
                                <div class="mb-4">
                                    <h6 class="border-bottom pb-2 text-primary">
                                        <i class="fas fa-folder-open me-2"></i>{{ $sectionName }}
                                    </h6>
                                    <div class="row">
                                        @foreach($fields as $field)
                                            @php
                                                $isAutoFilled = $field['is_auto_filled'] ?? false;
                                                $currentValue = $field['value'] ?? '';
                                                $fieldType = $field['field_type'] ?? 'text';
                                                $bgClass = $isAutoFilled ? 'bg-success bg-opacity-10' : 'bg-warning bg-opacity-10';
                                                $borderClass = $isAutoFilled ? 'border-success' : 'border-warning';
                                            @endphp
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label d-flex justify-content-between" for="field_{{ $field['field_name'] }}">
                                                    <span>
                                                        {{ $field['field_label'] }}
                                                        @if($field['is_required'] ?? false)
                                                            <span class="text-danger">*</span>
                                                        @endif
                                                    </span>
                                                    @if($isAutoFilled)
                                                        <span class="badge bg-success-subtle text-success">
                                                            <i class="fas fa-check me-1"></i>Auto
                                                        </span>
                                                    @else
                                                        <span class="badge bg-warning-subtle text-warning">
                                                            <i class="fas fa-edit me-1"></i>Manual
                                                        </span>
                                                    @endif
                                                </label>
                                                
                                                @if($fieldType === 'textarea')
                                                    <textarea name="fields[{{ $field['field_name'] }}]" 
                                                              id="field_{{ $field['field_name'] }}"
                                                              class="form-control {{ $bgClass }} {{ $borderClass }}"
                                                              rows="2"
                                                              {{ ($field['is_required'] ?? false) ? 'required' : '' }}>{{ $currentValue }}</textarea>
                                                @elseif($fieldType === 'date')
                                                    <input type="date" 
                                                           name="fields[{{ $field['field_name'] }}]" 
                                                           id="field_{{ $field['field_name'] }}"
                                                           class="form-control {{ $bgClass }} {{ $borderClass }}"
                                                           value="{{ $currentValue }}"
                                                           {{ ($field['is_required'] ?? false) ? 'required' : '' }}>
                                                @elseif($fieldType === 'number' || $fieldType === 'currency')
                                                    <input type="number" 
                                                           name="fields[{{ $field['field_name'] }}]" 
                                                           id="field_{{ $field['field_name'] }}"
                                                           class="form-control {{ $bgClass }} {{ $borderClass }}"
                                                           step="0.01"
                                                           value="{{ $currentValue }}"
                                                           {{ ($field['is_required'] ?? false) ? 'required' : '' }}>
                                                @elseif($fieldType === 'checkbox')
                                                    <div class="form-check {{ $bgClass }} p-2 rounded {{ $borderClass }} border">
                                                        <input type="checkbox" 
                                                               name="fields[{{ $field['field_name'] }}]" 
                                                               id="field_{{ $field['field_name'] }}"
                                                               class="form-check-input"
                                                               value="1"
                                                               {{ $currentValue ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="field_{{ $field['field_name'] }}">
                                                            Yes
                                                        </label>
                                                    </div>
                                                @else
                                                    <input type="text" 
                                                           name="fields[{{ $field['field_name'] }}]" 
                                                           id="field_{{ $field['field_name'] }}"
                                                           class="form-control {{ $bgClass }} {{ $borderClass }}"
                                                           maxlength="{{ $field['max_length'] ?? '' }}"
                                                           value="{{ $currentValue }}"
                                                           {{ ($field['is_required'] ?? false) ? 'required' : '' }}>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Stats Card -->
                <div class="card mb-4 border-success">
                    <div class="card-header bg-success text-white">
                        <strong><i class="fas fa-chart-pie me-2"></i>Auto-Fill Summary</strong>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <div class="display-6 text-success">{{ $mappingResult['auto_filled_count'] ?? 0 }}</div>
                                <small class="text-muted">Auto-Filled</small>
                            </div>
                            <div class="col-6">
                                <div class="display-6 text-warning">{{ ($mappingResult['total_fields'] ?? 0) - ($mappingResult['auto_filled_count'] ?? 0) }}</div>
                                <small class="text-muted">Need Review</small>
                            </div>
                        </div>
                        <div class="progress" style="height: 10px;">
                            @php
                                $total = $mappingResult['total_fields'] ?? 1;
                                $filled = $mappingResult['auto_filled_count'] ?? 0;
                                $percentage = $total > 0 ? round(($filled / $total) * 100) : 0;
                            @endphp
                            <div class="progress-bar bg-success" style="width: {{ $percentage }}%"></div>
                            <div class="progress-bar bg-warning" style="width: {{ 100 - $percentage }}%"></div>
                        </div>
                        <p class="text-center text-muted small mt-2 mb-0">
                            {{ $percentage }}% auto-filled
                        </p>
                    </div>
                </div>

                <!-- Output Options -->
                <div class="card mb-4 sticky-top" style="top: 20px;">
                    <div class="card-header">
                        <strong><i class="fas fa-cog me-2"></i>Output Options</strong>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <label class="form-label">Output Format</label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="output_format" 
                                       id="format_web" value="web" checked>
                                <label class="form-check-label" for="format_web">
                                    <i class="fas fa-globe me-1 text-primary"></i>Web Preview / Print
                                    <small class="d-block text-muted">View in browser and print</small>
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="output_format" 
                                       id="format_overlay" value="pdf_overlay">
                                <label class="form-check-label" for="format_overlay">
                                    <i class="fas fa-layer-group me-1 text-info"></i>PDF with Data Overlay
                                    <small class="d-block text-muted">Data placed on original form</small>
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="output_format" 
                                       id="format_fillable" value="pdf_fillable">
                                <label class="form-check-label" for="format_fillable">
                                    <i class="fas fa-file-pdf me-1 text-danger"></i>Fillable PDF
                                    <small class="d-block text-muted">If form has fillable fields</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="output_format" 
                                       id="format_data" value="data_only">
                                <label class="form-check-label" for="format_data">
                                    <i class="fas fa-table me-1 text-secondary"></i>Data Mapping Only
                                    <small class="d-block text-muted">View field-to-value mapping</small>
                                </label>
                            </div>
                        </div>

                        <hr>

                        <button type="submit" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-file-export me-2"></i>Generate Form
                        </button>
                        
                        <a href="{{ route('declaration-forms.show', $declarationForm) }}" 
                           class="btn btn-outline-secondary w-100 mt-2">
                            Cancel
                        </a>
                    </div>
                </div>

                <!-- Data Sources Info -->
                <div class="card">
                    <div class="card-header">
                        <strong><i class="fas fa-database me-2"></i>Data Sources</strong>
                    </div>
                    <div class="card-body small">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-file-invoice text-primary me-2"></i>
                                <strong>Invoice:</strong> #{{ $declarationForm->invoice?->invoice_number ?? 'N/A' }}
                            </li>
                            @if($declarationForm->shipment)
                            <li class="mb-2">
                                <i class="fas fa-ship text-info me-2"></i>
                                <strong>Shipment:</strong> {{ $declarationForm->shipment->primary_document_number ?? 'N/A' }}
                            </li>
                            @endif
                            <li class="mb-2">
                                <i class="fas fa-clipboard text-success me-2"></i>
                                <strong>Declaration:</strong> {{ $declarationForm->form_number }}
                            </li>
                            <li>
                                <i class="fas fa-flag text-warning me-2"></i>
                                <strong>Country:</strong> {{ $declarationForm->country?->name ?? 'N/A' }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit or refresh when contacts change (optional enhancement)
    const contactSelects = document.querySelectorAll('.contact-select');
    contactSelects.forEach(function(select) {
        select.addEventListener('change', function() {
            // Could trigger AJAX to refresh auto-filled values based on new contact
            // For now, just highlight the change
            this.classList.add('border-info');
        });
    });
});
</script>
@endpush
@endsection
