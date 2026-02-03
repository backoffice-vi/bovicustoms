@extends('layouts.app')

@section('title', 'Submit to Portal - ' . $declaration->form_number)

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-cloud-upload-alt me-2"></i>Submit to Portal</h2>
            <p class="text-muted mb-0">Declaration: {{ $declaration->form_number }}</p>
        </div>
        <a href="{{ route('declaration-forms.show', $declaration) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Declaration
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            @php
                $country = $declaration->country;
                $ftpEnabled = $country && $country->isFtpEnabled();
            @endphp

            @if($ftpEnabled)
            <!-- FTP Submission Option -->
            <div class="card mb-4 border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-upload me-2"></i>FTP Submission</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">{{ $country->name }} CAPS - FTP Upload</h5>
                            <p class="text-muted mb-2">
                                Submit directly to CAPS via FTP using the T12 file format.
                                <span class="badge bg-info">Recommended</span>
                            </p>
                            @php
                                $organization = $declaration->organization ?? auth()->user()->organization;
                                $hasCredentials = $organization && $organization->hasFtpCredentials($country->id);
                            @endphp
                            @if($hasCredentials)
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>FTP Credentials Configured</span>
                            @else
                                <span class="badge bg-warning"><i class="fas fa-exclamation-triangle me-1"></i>FTP Credentials Required</span>
                            @endif
                        </div>
                        <div class="btn-group">
                            @if($hasCredentials)
                                <a href="{{ route('ftp-submission.index', $declaration) }}" class="btn btn-primary">
                                    <i class="fas fa-upload me-1"></i>FTP Submit
                                </a>
                            @else
                                <a href="{{ route('settings.submission-credentials') }}" class="btn btn-outline-warning">
                                    <i class="fas fa-key me-1"></i>Configure Credentials
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Available Targets -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-globe me-2"></i>Web Portal Submission</h5>
                </div>
                <div class="card-body">
                    @if($targets->isEmpty())
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-circle fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No web portals configured for {{ $declaration->country?->name ?? 'this country' }}.</p>
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('admin.web-form-targets.create') }}" class="btn btn-primary">
                                    Configure Portal
                                </a>
                            @endif
                        </div>
                    @else
                        <div class="list-group">
                            @foreach($targets as $target)
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1">{{ $target->name }}</h5>
                                            <p class="mb-1 text-muted small">
                                                <a href="{{ $target->base_url }}" target="_blank">
                                                    {{ $target->base_url }}
                                                    <i class="fas fa-external-link-alt ms-1"></i>
                                                </a>
                                            </p>
                                            <div>
                                                @if($target->requires_ai)
                                                    <span class="badge bg-info" title="AI-assisted submission">
                                                        <i class="fas fa-robot"></i> AI Enabled
                                                    </span>
                                                @endif
                                                @if($target->isMapped())
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check"></i> Ready
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-exclamation-triangle"></i> Not Configured
                                                    </span>
                                                @endif
                                                <span class="badge bg-secondary">
                                                    {{ $target->pages->count() }} pages
                                                </span>
                                            </div>
                                        </div>
                                        <div class="btn-group">
                                            <a href="{{ route('web-submission.preview', [$declaration, $target]) }}" 
                                               class="btn btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i> Preview
                                            </a>
                                            @php
                                                $isCaps = str_contains(strtolower($target->base_url ?? ''), 'caps.gov.vg');
                                            @endphp
                                            @if($isCaps)
                                                {{-- CAPS gets Save and Submit options --}}
                                                <form action="{{ route('web-submission.submit', [$declaration, $target]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="action" value="save">
                                                    <button type="submit" class="btn btn-warning" 
                                                            @if(!$target->isMapped()) disabled title="Target not fully configured" @endif
                                                            title="Create and save TD without submitting for review">
                                                        <i class="fas fa-save me-1"></i> Save
                                                    </button>
                                                </form>
                                                <form action="{{ route('web-submission.submit', [$declaration, $target]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="action" value="submit">
                                                    <button type="submit" class="btn btn-success" 
                                                            @if(!$target->isMapped()) disabled title="Target not fully configured" @endif
                                                            title="Create TD and submit for customs review">
                                                        <i class="fas fa-paper-plane me-1"></i> Submit
                                                    </button>
                                                </form>
                                            @else
                                                <form action="{{ route('web-submission.submit', [$declaration, $target]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success" 
                                                            @if(!$target->isMapped()) disabled title="Target not fully configured" @endif>
                                                        <i class="fas fa-paper-plane me-1"></i> Submit
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Submission History -->
            @if($submissions->isNotEmpty())
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Submission History</h5>
                        <a href="{{ route('web-submission.history', $declaration) }}" class="btn btn-sm btn-outline-secondary">
                            View All
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Portal</th>
                                        <th>Status</th>
                                        <th>Reference</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($submissions->take(5) as $submission)
                                        <tr>
                                            <td>{{ $submission->target->name ?? 'Unknown' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $submission->status_color }}">
                                                    {{ $submission->status_label }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($submission->external_reference)
                                                    <code>{{ $submission->external_reference }}</code>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $submission->created_at->format('M j, g:ia') }}</td>
                                            <td>
                                                <a href="{{ route('web-submission.result', [$declaration, $submission]) }}" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                                @if($submission->can_retry)
                                                    <form action="{{ route('web-submission.retry', $submission) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-warning">
                                                            Retry
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <!-- Declaration Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Declaration Summary</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Form #</dt>
                        <dd class="col-sm-7">{{ $declaration->form_number }}</dd>

                        <dt class="col-sm-5">Country</dt>
                        <dd class="col-sm-7">{{ $declaration->country?->name ?? '-' }}</dd>

                        <dt class="col-sm-5">CIF Value</dt>
                        <dd class="col-sm-7">${{ number_format($declaration->cif_value ?? 0, 2) }}</dd>

                        <dt class="col-sm-5">Total Duty</dt>
                        <dd class="col-sm-7">${{ number_format($declaration->total_duty ?? 0, 2) }}</dd>

                        @if($declaration->shipment)
                        <dt class="col-sm-5">B/L #</dt>
                        <dd class="col-sm-7">{{ $declaration->shipment->bill_of_lading_number ?? '-' }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Help -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">How It Works</h5>
                </div>
                <div class="card-body">
                    <ol class="small text-muted mb-0">
                        <li class="mb-2">Select a portal from the list</li>
                        <li class="mb-2">Click "Preview" to see how data will be mapped</li>
                        <li class="mb-2">Click "Submit" to send to the external portal</li>
                        <li class="mb-2">The system will automatically fill forms and submit</li>
                        <li>You'll receive a reference number upon success</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
