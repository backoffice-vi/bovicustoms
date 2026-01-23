@extends('layouts.app')

@section('title', 'Select Form Templates - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-file-export me-2"></i>Generate Official Forms</h2>
            <p class="text-muted mb-0">
                Select a form template to fill for declaration 
                <strong>{{ $declarationForm->form_number }}</strong>
            </p>
        </div>
        <a href="{{ route('declaration-forms.show', $declarationForm) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Declaration Summary -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <small class="text-muted">Declaration</small>
                    <div class="fw-bold">{{ $declarationForm->form_number }}</div>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Country</small>
                    <div class="fw-bold">{{ $declarationForm->country?->name ?? 'N/A' }}</div>
                </div>
                <div class="col-md-4">
                    <small class="text-muted">Invoice</small>
                    <div class="fw-bold">
                        @if($declarationForm->invoice)
                            #{{ $declarationForm->invoice->invoice_number }}
                        @else
                            N/A
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Template Selection -->
    <h5 class="mb-3">
        <i class="fas fa-clipboard-list me-2"></i>
        Available Form Templates for {{ $declarationForm->country?->name ?? 'this country' }}
    </h5>

    <div class="row">
        @foreach($templates as $template)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-3">
                            <div class="bg-primary bg-opacity-10 rounded p-3 me-3">
                                <i class="fas fa-file-{{ $template->file_type === 'pdf' ? 'pdf text-danger' : 'alt text-primary' }} fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-1">{{ $template->name }}</h5>
                                <span class="badge bg-{{ $template->form_type === 'customs_declaration' ? 'primary' : ($template->form_type === 'import_permit' ? 'success' : ($template->form_type === 'certificate_of_origin' ? 'info' : 'secondary')) }}">
                                    {{ $template->form_type_label }}
                                </span>
                            </div>
                        </div>
                        
                        @if($template->description)
                            <p class="card-text text-muted small">{{ Str::limit($template->description, 100) }}</p>
                        @endif

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted">
                                <i class="fas fa-file me-1"></i>{{ strtoupper($template->file_type) }}
                            </small>
                            
                            <form action="{{ route('declaration-forms.analyze-template', $declarationForm) }}" method="POST">
                                @csrf
                                <input type="hidden" name="template_id" value="{{ $template->id }}">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-magic me-1"></i>Fill This Form
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent">
                        <small class="text-muted">
                            Uploaded {{ $template->created_at->diffForHumans() }}
                        </small>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($templates->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                <h4>No Form Templates Available</h4>
                <p class="text-muted">
                    There are no form templates configured for {{ $declarationForm->country?->name ?? 'this country' }}.
                    Please contact an administrator to upload the required templates.
                </p>
            </div>
        </div>
    @endif

    <!-- Info Box -->
    <div class="card bg-light mt-4">
        <div class="card-body">
            <h6><i class="fas fa-info-circle me-2 text-info"></i>How it works</h6>
            <ol class="mb-0 small">
                <li>Select a form template above</li>
                <li>Our AI will analyze the form and extract all fields that need to be filled</li>
                <li>Select your saved trade contacts (shippers, consignees, brokers) or add new ones</li>
                <li>Review auto-filled data and provide any missing information</li>
                <li>Preview and download or print the completed form</li>
            </ol>
        </div>
    </div>
</div>
@endsection
