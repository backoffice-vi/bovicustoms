@extends('layouts.app')

@section('title', 'FTP Submission - ' . ($declaration->form_number ?? 'Declaration'))

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('declaration-forms.index') }}">Declarations</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('declaration-forms.show', $declaration) }}">{{ $declaration->form_number ?? 'View' }}</a></li>
                    <li class="breadcrumb-item active">FTP Submission</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 mt-2">
                <i class="fas fa-upload text-primary me-2"></i>FTP Submission
            </h1>
        </div>
        <a href="{{ route('declaration-forms.show', $declaration) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Declaration
        </a>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="row">
        <!-- Declaration Summary -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Declaration Summary</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th>Form Number:</th>
                            <td>{{ $declaration->form_number ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Country:</th>
                            <td>{{ $country->flag_emoji }} {{ $country->name }}</td>
                        </tr>
                        <tr>
                            <th>Declaration Date:</th>
                            <td>{{ $declaration->declaration_date?->format('d/m/Y') ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Arrival Date:</th>
                            <td>{{ $declaration->arrival_date?->format('d/m/Y') ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>B/L / AWB:</th>
                            <td>{{ $declaration->bill_of_lading_number ?? $declaration->awb_number ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>FOB Value:</th>
                            <td>{{ $declaration->formatted_fob }}</td>
                        </tr>
                        <tr>
                            <th>CIF Value:</th>
                            <td>{{ $declaration->formatted_cif }}</td>
                        </tr>
                        <tr>
                            <th>Total Duty:</th>
                            <td><strong>{{ $declaration->formatted_total_duty }}</strong></td>
                        </tr>
                        @if($consignee)
                        <tr>
                            <th>Consignee:</th>
                            <td>{{ $consignee->company_name }}</td>
                        </tr>
                        <tr>
                            <th>Importer ID:</th>
                            <td>
                                @if($consigneeTraderId)
                                    <code>{{ $consigneeTraderId }}</code>
                                @else
                                    <span class="text-muted">Not set</span>
                                @endif
                            </td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <!-- FTP Submission -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Submit via FTP to {{ $country->name }} CAPS</h5>
                </div>
                <div class="card-body">
                    @if($credentials)
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            FTP credentials configured for <strong>Trader ID: {{ $credentials->trader_id }}</strong>
                        </div>

                        @if($consigneeTraderId && $consigneeTraderId !== $credentials->trader_id)
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Consignee <strong>{{ $consignee->company_name }}</strong> has a different 
                            Importer ID: <code>{{ $consigneeTraderId }}</code>. The T12 file will use this ID for the importer fields.
                        </div>
                        @elseif($consigneeTraderId)
                        <div class="alert alert-light border">
                            <i class="fas fa-user me-2"></i>
                            Consignee: <strong>{{ $consignee->company_name }}</strong> - Importer ID: <code>{{ $consigneeTraderId }}</code>
                        </div>
                        @endif

                        <div class="d-flex gap-2">
                            <a href="{{ route('ftp-submission.preview', $declaration) }}" class="btn btn-primary btn-lg">
                                <i class="fas fa-eye me-2"></i>Preview T12 File
                            </a>
                            <a href="{{ route('ftp-submission.download', $declaration) }}" class="btn btn-outline-secondary">
                                <i class="fas fa-download me-2"></i>Download T12 File
                            </a>
                        </div>

                        <hr>

                        <h6><i class="fas fa-info-circle me-2"></i>About FTP Submission</h6>
                        <p class="text-muted">
                            FTP submission generates a T12 file following the CAPS specification and uploads it directly 
                            to the customs FTP server. You will receive email confirmation from CAPS once the file is processed.
                        </p>

                        <div class="alert alert-info">
                            <strong>Note:</strong> Make sure all declaration data is complete and accurate before submitting.
                            Once submitted, amendments must be filed as separate T12 files.
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>FTP credentials not configured.</strong>
                            <p class="mb-0 mt-2">
                                Please configure your FTP credentials for {{ $country->name }} before you can submit via FTP.
                            </p>
                        </div>

                        @if($consigneeTraderId)
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Importer ID found:</strong> The consignee <strong>{{ $consignee->company_name }}</strong> 
                            has Importer ID <code>{{ $consigneeTraderId }}</code> which will be used in the T12 file.
                            You only need to configure your FTP login credentials (username/password).
                        </div>
                        @endif

                        <a href="{{ route('settings.submission-credentials') }}" class="btn btn-primary">
                            <i class="fas fa-key me-2"></i>Configure FTP Credentials
                        </a>
                    @endif
                </div>
            </div>

            <!-- Submission History -->
            @if($submissions->count() > 0)
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>FTP Submission History</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Status</th>
                                    <th>By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($submissions as $submission)
                                <tr>
                                    <td>{{ $submission->created_at->format('d/m/Y H:i') }}</td>
                                    <td><code>{{ $submission->external_reference ?? 'N/A' }}</code></td>
                                    <td>
                                        <span class="badge bg-{{ $submission->status_color }}">
                                            {{ $submission->status_label }}
                                        </span>
                                    </td>
                                    <td>{{ $submission->user?->name ?? 'System' }}</td>
                                    <td>
                                        <a href="{{ route('ftp-submission.result', ['declaration' => $declaration, 'submission' => $submission]) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
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
    </div>
</div>
@endsection
