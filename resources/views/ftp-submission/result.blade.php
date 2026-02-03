@extends('layouts.app')

@section('title', 'FTP Submission Result')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('declaration-forms.index') }}">Declarations</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ftp-submission.index', $declaration) }}">FTP Submission</a></li>
                    <li class="breadcrumb-item active">Result</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 mt-2">
                <i class="fas fa-{{ $submission->is_successful ? 'check-circle text-success' : 'times-circle text-danger' }} me-2"></i>
                Submission {{ $submission->is_successful ? 'Successful' : 'Failed' }}
            </h1>
        </div>
        <div>
            <a href="{{ route('ftp-submission.index', $declaration) }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to FTP Submission
            </a>
            <a href="{{ route('declaration-forms.show', $declaration) }}" class="btn btn-outline-primary">
                <i class="fas fa-file-alt me-1"></i> View Declaration
            </a>
        </div>
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
        <!-- Result Card -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header {{ $submission->is_successful ? 'bg-success' : 'bg-danger' }} text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-{{ $submission->is_successful ? 'check-circle' : 'times-circle' }} me-2"></i>
                        {{ $submission->is_successful ? 'Submission Successful' : 'Submission Failed' }}
                    </h5>
                </div>
                <div class="card-body">
                    @if($submission->is_successful)
                        <div class="text-center mb-4">
                            <i class="fas fa-check-circle fa-5x text-success mb-3"></i>
                            <h4>Your T12 file has been uploaded!</h4>
                            <p class="text-muted">
                                The file has been submitted to the CAPS FTP server.
                                You will receive an email confirmation from customs.
                            </p>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>What happens next?</h6>
                            <ol class="mb-0 small">
                                <li>CAPS will process your T12 file</li>
                                <li>You'll receive an email with the Declaration Receipt</li>
                                <li>If there are issues, you'll receive a System Query Report</li>
                                <li>Once accepted, you'll receive the T12 Payment Summary</li>
                            </ol>
                        </div>
                    @else
                        <div class="text-center mb-4">
                            <i class="fas fa-times-circle fa-5x text-danger mb-3"></i>
                            <h4>Submission Failed</h4>
                            <p class="text-muted">
                                The FTP upload was not successful. Please review the error and try again.
                            </p>
                        </div>

                        @if($submission->error_message)
                            <div class="alert alert-danger">
                                <h6><i class="fas fa-exclamation-triangle me-2"></i>Error Details</h6>
                                <p class="mb-0">{{ $submission->error_message }}</p>
                            </div>
                        @endif

                        @if($submission->can_retry)
                            <form action="{{ route('ftp-submission.retry', $submission) }}" method="POST" class="text-center">
                                @csrf
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-redo me-2"></i>Retry Submission
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <!-- Submission Details -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Submission Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th>Status:</th>
                            <td>
                                <span class="badge bg-{{ $submission->status_color }}">
                                    {{ $submission->status_label }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Type:</th>
                            <td>
                                <span class="badge bg-primary">
                                    <i class="fas fa-upload me-1"></i>{{ $submission->submission_type_label }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>File Reference:</th>
                            <td><code>{{ $submission->external_reference ?? 'N/A' }}</code></td>
                        </tr>
                        <tr>
                            <th>Submitted At:</th>
                            <td>{{ $submission->submitted_at?->format('d/m/Y H:i:s') ?? $submission->created_at->format('d/m/Y H:i:s') }}</td>
                        </tr>
                        <tr>
                            <th>Submitted By:</th>
                            <td>{{ $submission->user?->name ?? 'System' }}</td>
                        </tr>
                        @if($submission->request_data)
                            <tr>
                                <th>Trader ID:</th>
                                <td><code>{{ $submission->request_data['trader_id'] ?? 'N/A' }}</code></td>
                            </tr>
                            <tr>
                                <th>Lines:</th>
                                <td>{{ $submission->request_data['line_count'] ?? 'N/A' }}</td>
                            </tr>
                            <tr>
                                <th>Items:</th>
                                <td>{{ $submission->request_data['item_count'] ?? 'N/A' }}</td>
                            </tr>
                        @endif
                        @if($submission->response_data && isset($submission->response_data['remote_path']))
                            <tr>
                                <th>Remote Path:</th>
                                <td><code>{{ $submission->response_data['remote_path'] }}</code></td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>

            <!-- Declaration Info -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Declaration</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th>Form Number:</th>
                            <td>{{ $declaration->form_number ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Country:</th>
                            <td>{{ $declaration->country->flag_emoji ?? '' }} {{ $declaration->country->name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>B/L / AWB:</th>
                            <td>{{ $declaration->bill_of_lading_number ?? $declaration->awb_number ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Declaration Status:</th>
                            <td>
                                <span class="badge bg-{{ $declaration->submission_status_color }}">
                                    {{ $declaration->submission_status_label }}
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
