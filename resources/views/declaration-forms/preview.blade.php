@extends('layouts.app')

@section('title', 'Form Preview - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-file-alt me-2"></i>{{ $filledForm->template?->name }}</h2>
            <p class="text-muted mb-0">
                Declaration {{ $declarationForm->form_number }} | 
                {{ $filledForm->output_format_label }}
            </p>
        </div>
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print me-2"></i>Print
            </button>
            <a href="{{ route('declaration-forms.fill', [$declarationForm, $filledForm]) }}" class="btn btn-outline-secondary">
                <i class="fas fa-edit me-2"></i>Edit
            </a>
            <a href="{{ route('declaration-forms.show', $declarationForm) }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-print-none" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <!-- Form Preview -->
            <div class="card mb-4" id="printable-area">
                <div class="card-header d-print-none">
                    <strong><i class="fas fa-eye me-2"></i>Form Data Preview</strong>
                </div>
                <div class="card-body">
                    <!-- Form Header -->
                    <div class="text-center mb-4 border-bottom pb-3">
                        <h4 class="mb-1">{{ $filledForm->template?->name }}</h4>
                        <p class="text-muted mb-0">{{ $declarationForm->country?->name }} Customs Form</p>
                    </div>

                    @if(empty($fieldsBySection))
                        <p class="text-muted">No field data available.</p>
                    @else
                        @foreach($fieldsBySection as $sectionName => $fields)
                            <div class="mb-4">
                                <h5 class="border-bottom pb-2 text-primary">
                                    <i class="fas fa-folder me-2"></i>{{ $sectionName }}
                                </h5>
                                <table class="table table-sm table-bordered">
                                    <tbody>
                                        @foreach($fields as $field)
                                            <tr>
                                                <td class="bg-light" style="width: 40%;">
                                                    <strong>{{ $field['field_label'] }}</strong>
                                                    @if($field['is_required'] ?? false)
                                                        <span class="text-danger">*</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if(!empty($field['value']))
                                                        {{ $field['value'] }}
                                                        @if($field['is_auto_filled'] ?? false)
                                                            <span class="badge bg-info ms-1 d-print-none" title="Auto-filled">
                                                                <i class="fas fa-magic"></i>
                                                            </span>
                                                        @endif
                                                    @else
                                                        <span class="text-muted fst-italic">Not provided</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    @endif

                    <!-- Signature Section -->
                    <div class="mt-5 pt-4 border-top">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <strong>Declarant's Signature:</strong>
                                    <div class="border-bottom mt-4" style="width: 80%;"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <strong>Date:</strong>
                                    <div class="border-bottom mt-4" style="width: 80%;"></div>
                                </div>
                            </div>
                        </div>
                        <p class="small text-muted mt-3">
                            I hereby declare that the information provided above is true and accurate to the best of my knowledge.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 d-print-none">
            <!-- Status Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-check-circle me-2"></i>Form Status</strong>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge bg-{{ $filledForm->status === 'complete' ? 'success' : ($filledForm->status === 'error' ? 'danger' : 'warning') }} me-2">
                            {{ $filledForm->status_label }}
                        </span>
                        <span class="text-muted">{{ $filledForm->output_format_label }}</span>
                    </div>
                    
                    <dl class="mb-0 small">
                        <dt>Created</dt>
                        <dd>{{ $filledForm->created_at->format('M d, Y H:i') }}</dd>
                        
                        <dt>Template</dt>
                        <dd>{{ $filledForm->template?->name }}</dd>
                        
                        <dt>Auto-filled Fields</dt>
                        <dd>
                            @php
                                $autoFilled = collect($filledForm->field_mappings ?? [])->filter(fn($f) => $f['is_auto_filled'] ?? false)->count();
                                $total = count($filledForm->field_mappings ?? []);
                            @endphp
                            {{ $autoFilled }} of {{ $total }} fields
                        </dd>
                    </dl>
                </div>
            </div>

            <!-- Selected Contacts -->
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-users me-2"></i>Selected Contacts</strong>
                </div>
                <div class="card-body">
                    @if($filledForm->shipperContact)
                        <div class="mb-3">
                            <strong class="small text-muted">Shipper</strong>
                            <div>{{ $filledForm->shipperContact->company_name }}</div>
                        </div>
                    @endif
                    
                    @if($filledForm->consigneeContact)
                        <div class="mb-3">
                            <strong class="small text-muted">Consignee</strong>
                            <div>{{ $filledForm->consigneeContact->company_name }}</div>
                        </div>
                    @endif
                    
                    @if($filledForm->brokerContact)
                        <div class="mb-3">
                            <strong class="small text-muted">Broker</strong>
                            <div>{{ $filledForm->brokerContact->company_name }}</div>
                        </div>
                    @endif
                    
                    @if(!$filledForm->shipperContact && !$filledForm->consigneeContact && !$filledForm->brokerContact)
                        <p class="text-muted mb-0">No contacts selected</p>
                    @endif
                </div>
            </div>

            <!-- Actions -->
            <div class="card">
                <div class="card-header">
                    <strong><i class="fas fa-download me-2"></i>Actions</strong>
                </div>
                <div class="card-body">
                    <button onclick="window.print()" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-print me-2"></i>Print Form
                    </button>
                    <a href="{{ route('declaration-forms.fill', [$declarationForm, $filledForm]) }}" 
                       class="btn btn-outline-secondary w-100 mb-2">
                        <i class="fas fa-edit me-2"></i>Edit Form Data
                    </a>
                    <a href="{{ route('declaration-forms.select-templates', $declarationForm) }}" 
                       class="btn btn-outline-secondary w-100">
                        <i class="fas fa-plus me-2"></i>Fill Another Template
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .d-print-none {
        display: none !important;
    }
    
    .navbar, footer {
        display: none !important;
    }
    
    .container {
        max-width: 100% !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .card-header {
        display: none !important;
    }
    
    body {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
}
</style>
@endsection
