@extends('layouts.app')

@section('title', 'Fill Form - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-edit me-2"></i>Fill: {{ $filledForm->template?->name }}</h2>
            <p class="text-muted mb-0">
                Declaration {{ $declarationForm->form_number }} | 
                {{ count($filledForm->extracted_fields ?? []) }} fields detected
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

    <form action="{{ route('declaration-forms.process-fill', [$declarationForm, $filledForm]) }}" method="POST" id="fillForm">
        @csrf
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Step 1: Select Trade Contacts -->
                <div class="card mb-4">
                    <div class="card-header">
                        <strong><i class="fas fa-users me-2"></i>Step 1: Select Trade Contacts</strong>
                        <small class="text-muted d-block">Choose from your saved contacts or add new ones</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @if(in_array('shipper', $requiredContactTypes) || $shippers->count() > 0)
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-shipping-fast text-info me-1"></i>Shipper / Exporter
                                    @if(in_array('shipper', $requiredContactTypes))
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <select name="shipper_contact_id" class="form-select contact-select" data-type="shipper">
                                    <option value="">-- Select Shipper --</option>
                                    @foreach($shippers as $contact)
                                        <option value="{{ $contact->id }}" {{ $contact->is_default ? 'selected' : '' }}>
                                            {{ $contact->display_name }}
                                            @if($contact->is_default) (Default) @endif
                                        </option>
                                    @endforeach
                                </select>
                                <a href="{{ route('trade-contacts.create', ['type' => 'shipper']) }}" 
                                   class="btn btn-link btn-sm p-0 mt-1" target="_blank">
                                    <i class="fas fa-plus me-1"></i>Add new shipper
                                </a>
                            </div>
                            @endif

                            @if(in_array('consignee', $requiredContactTypes) || $consignees->count() > 0)
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-warehouse text-success me-1"></i>Consignee / Importer
                                    @if(in_array('consignee', $requiredContactTypes))
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <select name="consignee_contact_id" class="form-select contact-select" data-type="consignee">
                                    <option value="">-- Select Consignee --</option>
                                    @foreach($consignees as $contact)
                                        <option value="{{ $contact->id }}" {{ $contact->is_default ? 'selected' : '' }}>
                                            {{ $contact->display_name }}
                                            @if($contact->is_default) (Default) @endif
                                        </option>
                                    @endforeach
                                </select>
                                <a href="{{ route('trade-contacts.create', ['type' => 'consignee']) }}" 
                                   class="btn btn-link btn-sm p-0 mt-1" target="_blank">
                                    <i class="fas fa-plus me-1"></i>Add new consignee
                                </a>
                            </div>
                            @endif

                            @if(in_array('broker', $requiredContactTypes) || $brokers->count() > 0)
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-user-tie text-warning me-1"></i>Customs Broker
                                    @if(in_array('broker', $requiredContactTypes))
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <select name="broker_contact_id" class="form-select contact-select" data-type="broker">
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $contact)
                                        <option value="{{ $contact->id }}" {{ $contact->is_default ? 'selected' : '' }}>
                                            {{ $contact->display_name }}
                                            @if($contact->is_default) (Default) @endif
                                        </option>
                                    @endforeach
                                </select>
                                <a href="{{ route('trade-contacts.create', ['type' => 'broker']) }}" 
                                   class="btn btn-link btn-sm p-0 mt-1" target="_blank">
                                    <i class="fas fa-plus me-1"></i>Add new broker
                                </a>
                            </div>
                            @endif

                            @if(in_array('bank', $requiredContactTypes) || $banks->count() > 0)
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-university text-secondary me-1"></i>Bank
                                </label>
                                <select name="bank_contact_id" class="form-select contact-select" data-type="bank">
                                    <option value="">-- Select Bank --</option>
                                    @foreach($banks as $contact)
                                        <option value="{{ $contact->id }}" {{ $contact->is_default ? 'selected' : '' }}>
                                            {{ $contact->display_name }}
                                            @if($contact->is_default) (Default) @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif

                            @if(in_array('notify_party', $requiredContactTypes) || $notifyParties->count() > 0)
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-bell text-info me-1"></i>Notify Party
                                </label>
                                <select name="notify_party_contact_id" class="form-select contact-select" data-type="notify_party">
                                    <option value="">-- Select Notify Party --</option>
                                    @foreach($notifyParties as $contact)
                                        <option value="{{ $contact->id }}" {{ $contact->is_default ? 'selected' : '' }}>
                                            {{ $contact->display_name }}
                                            @if($contact->is_default) (Default) @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Step 2: Additional Form Fields -->
                <div class="card mb-4">
                    <div class="card-header">
                        <strong><i class="fas fa-clipboard-list me-2"></i>Step 2: Additional Information</strong>
                        <small class="text-muted d-block">Fill in any fields that can't be auto-populated</small>
                    </div>
                    <div class="card-body">
                        @if(empty($fieldsBySection))
                            <p class="text-muted">No additional fields detected in this form.</p>
                        @else
                            @foreach($fieldsBySection as $sectionName => $fields)
                                <h6 class="border-bottom pb-2 mb-3 mt-4 first:mt-0">{{ $sectionName }}</h6>
                                <div class="row">
                                    @foreach($fields as $field)
                                        @if(in_array($field['data_source'] ?? 'manual', ['manual', 'shipping', 'other']))
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label" for="field_{{ $field['field_name'] }}">
                                                    {{ $field['field_label'] }}
                                                    @if($field['is_required'] ?? false)
                                                        <span class="text-danger">*</span>
                                                    @endif
                                                </label>
                                                
                                                @if(($field['field_type'] ?? 'text') === 'textarea')
                                                    <textarea name="fields[{{ $field['field_name'] }}]" 
                                                              id="field_{{ $field['field_name'] }}"
                                                              class="form-control"
                                                              rows="2"
                                                              placeholder="{{ $field['hints'] ?? '' }}"
                                                              {{ ($field['is_required'] ?? false) ? 'required' : '' }}></textarea>
                                                @elseif(($field['field_type'] ?? 'text') === 'date')
                                                    <input type="date" 
                                                           name="fields[{{ $field['field_name'] }}]" 
                                                           id="field_{{ $field['field_name'] }}"
                                                           class="form-control"
                                                           {{ ($field['is_required'] ?? false) ? 'required' : '' }}>
                                                @elseif(($field['field_type'] ?? 'text') === 'number' || ($field['field_type'] ?? 'text') === 'currency')
                                                    <input type="number" 
                                                           name="fields[{{ $field['field_name'] }}]" 
                                                           id="field_{{ $field['field_name'] }}"
                                                           class="form-control"
                                                           step="0.01"
                                                           placeholder="{{ $field['hints'] ?? '' }}"
                                                           {{ ($field['is_required'] ?? false) ? 'required' : '' }}>
                                                @elseif(($field['field_type'] ?? 'text') === 'checkbox')
                                                    <div class="form-check">
                                                        <input type="checkbox" 
                                                               name="fields[{{ $field['field_name'] }}]" 
                                                               id="field_{{ $field['field_name'] }}"
                                                               class="form-check-input"
                                                               value="1">
                                                        <label class="form-check-label" for="field_{{ $field['field_name'] }}">
                                                            Yes
                                                        </label>
                                                    </div>
                                                @else
                                                    <input type="text" 
                                                           name="fields[{{ $field['field_name'] }}]" 
                                                           id="field_{{ $field['field_name'] }}"
                                                           class="form-control"
                                                           maxlength="{{ $field['max_length'] ?? '' }}"
                                                           placeholder="{{ $field['hints'] ?? '' }}"
                                                           {{ ($field['is_required'] ?? false) ? 'required' : '' }}>
                                                @endif
                                                
                                                @if($field['hints'] ?? null)
                                                    <small class="text-muted">{{ $field['hints'] }}</small>
                                                @endif
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
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

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-check me-2"></i>Generate Form
                        </button>
                        
                        <a href="{{ route('declaration-forms.show', $declarationForm) }}" 
                           class="btn btn-outline-secondary w-100 mt-2">
                            Cancel
                        </a>
                    </div>
                </div>

                <!-- Summary Card -->
                <div class="card">
                    <div class="card-header">
                        <strong><i class="fas fa-info-circle me-2"></i>Auto-fill Summary</strong>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">
                            The following data will be automatically filled from your invoice and selected contacts:
                        </p>
                        <ul class="small mb-0">
                            <li><strong>Invoice:</strong> Number, date, totals</li>
                            <li><strong>Goods:</strong> Descriptions, quantities, HS codes</li>
                            <li><strong>Contacts:</strong> Names, addresses, identifiers</li>
                            <li><strong>Declaration:</strong> Form number, date</li>
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
    // Reload page if user adds a new contact in a new tab
    window.addEventListener('focus', function() {
        // Could implement AJAX refresh of contact dropdowns here
    });
});
</script>
@endpush
@endsection
