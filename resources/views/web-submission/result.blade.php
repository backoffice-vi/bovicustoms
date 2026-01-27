@extends('layouts.app')

@section('title', 'Submission Result - ' . $declaration->form_number)

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-cloud-upload-alt me-2"></i>Submission Result</h2>
            <p class="text-muted mb-0">{{ $submission->target->name ?? 'Portal' }} - {{ $declaration->form_number }}</p>
        </div>
        <div class="btn-group">
            <a href="{{ route('web-submission.index', $declaration) }}" class="btn btn-outline-secondary">
                <i class="fas fa-list me-1"></i> All Portals
            </a>
            <a href="{{ route('declaration-forms.show', $declaration) }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Declaration
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Result Card -->
            <div class="card mb-4 border-{{ $submission->is_successful ? 'success' : 'danger' }}">
                <div class="card-header bg-{{ $submission->is_successful ? 'success' : 'danger' }} text-white">
                    <h5 class="card-title mb-0">
                        @if($submission->is_successful)
                            <i class="fas fa-check-circle me-2"></i>Submission Successful
                        @else
                            <i class="fas fa-times-circle me-2"></i>Submission Failed
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    @if($submission->is_successful && $submission->external_reference)
                        <div class="text-center py-4">
                            <p class="text-muted mb-2">Reference Number</p>
                            <h2 class="text-primary">
                                <code>{{ $submission->external_reference }}</code>
                            </h2>
                            <p class="text-muted mt-3 mb-0">
                                Submitted at {{ $submission->completed_at?->format('M j, Y g:i A') }}
                            </p>
                        </div>
                    @elseif($submission->status === 'failed')
                        <div class="alert alert-danger mb-0">
                            <strong>Error:</strong> {{ $submission->error_message ?? 'Unknown error occurred' }}
                        </div>
                        @if($submission->can_retry)
                            <div class="text-center mt-4">
                                <form action="{{ route('web-submission.retry', $submission) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-redo me-2"></i>Retry Submission
                                    </button>
                                </form>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4">
                            <span class="badge bg-{{ $submission->status_color }} fs-5">
                                {{ $submission->status_label }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Submission Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Submission Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Portal</dt>
                                <dd class="col-sm-7">{{ $submission->target->name ?? '-' }}</dd>

                                <dt class="col-sm-5">Status</dt>
                                <dd class="col-sm-7">
                                    <span class="badge bg-{{ $submission->status_color }}">
                                        {{ $submission->status_label }}
                                    </span>
                                </dd>

                                <dt class="col-sm-5">Duration</dt>
                                <dd class="col-sm-7">{{ $submission->formatted_duration }}</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Started</dt>
                                <dd class="col-sm-7">{{ $submission->started_at?->format('g:i:s A') ?? '-' }}</dd>

                                <dt class="col-sm-5">Completed</dt>
                                <dd class="col-sm-7">{{ $submission->completed_at?->format('g:i:s A') ?? '-' }}</dd>

                                <dt class="col-sm-5">Retries</dt>
                                <dd class="col-sm-7">{{ $submission->retry_count }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI Decisions (if any) -->
            @if(!empty($submission->ai_decisions))
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-robot me-2"></i>AI Decisions</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Situation</th>
                                        <th>Decision</th>
                                        <th>Reasoning</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($submission->ai_decisions as $decision)
                                        <tr>
                                            <td>{{ $decision['situation'] ?? '-' }}</td>
                                            <td><code>{{ $decision['decision'] ?? '-' }}</code></td>
                                            <td class="small">{{ $decision['reasoning'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Submission Log -->
            @if(!empty($submission->submission_log))
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-list-alt me-2"></i>Submission Log</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="submission-log" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-striped mb-0">
                                <tbody>
                                    @foreach($submission->submission_log as $entry)
                                        <tr>
                                            <td style="width: 180px;" class="text-muted small">
                                                {{ \Carbon\Carbon::parse($entry['timestamp'])->format('H:i:s.v') }}
                                            </td>
                                            <td>
                                                @if(($entry['level'] ?? 'info') === 'error')
                                                    <span class="text-danger">{{ $entry['message'] }}</span>
                                                @elseif(($entry['level'] ?? 'info') === 'success')
                                                    <span class="text-success">{{ $entry['message'] }}</span>
                                                @else
                                                    {{ $entry['message'] }}
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
            <!-- Screenshots -->
            @if(!empty($submission->screenshots))
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-camera me-2"></i>Screenshots</h5>
                    </div>
                    <div class="card-body">
                        @foreach($submission->screenshots as $index => $screenshot)
                            @php
                                $filename = basename($screenshot);
                            @endphp
                            <div class="mb-2">
                                <a href="{{ asset('storage/playwright-screenshots/' . $filename) }}" target="_blank" class="text-decoration-none">
                                    <i class="fas fa-image me-2"></i>{{ $filename }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Declaration Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Declaration</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Form #</dt>
                        <dd class="col-sm-7">
                            <a href="{{ route('declaration-forms.show', $declaration) }}">
                                {{ $declaration->form_number }}
                            </a>
                        </dd>

                        <dt class="col-sm-5">Country</dt>
                        <dd class="col-sm-7">{{ $declaration->country?->name ?? '-' }}</dd>

                        <dt class="col-sm-5">CIF Value</dt>
                        <dd class="col-sm-7">${{ number_format($declaration->cif_value ?? 0, 2) }}</dd>

                        <dt class="col-sm-5">Total Duty</dt>
                        <dd class="col-sm-7">${{ number_format($declaration->total_duty ?? 0, 2) }}</dd>
                    </dl>
                </div>
            </div>

            <!-- Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        @if($submission->can_retry)
                            <form action="{{ route('web-submission.retry', $submission) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="fas fa-redo me-2"></i>Retry Submission
                                </button>
                            </form>
                        @endif
                        <a href="{{ route('web-submission.index', $declaration) }}" class="btn btn-outline-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit to Another Portal
                        </a>
                        <a href="{{ route('declaration-forms.show', $declaration) }}" class="btn btn-outline-secondary">
                            <i class="fas fa-file-alt me-2"></i>View Declaration
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
