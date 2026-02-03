@extends('layouts.app')

@section('title', 'FTP Submission History')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('declaration-forms.index') }}">Declarations</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ftp-submission.index', $declaration) }}">FTP Submission</a></li>
                    <li class="breadcrumb-item active">History</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 mt-2">
                <i class="fas fa-history text-primary me-2"></i>FTP Submission History
            </h1>
        </div>
        <a href="{{ route('ftp-submission.index', $declaration) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-file-alt me-2"></i>
                Declaration: {{ $declaration->form_number ?? 'N/A' }}
            </h5>
        </div>
        <div class="card-body p-0">
            @if($submissions->isEmpty())
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No FTP submissions yet</h5>
                    <p class="text-muted">Submit your first FTP file to see history here.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <th>Trader ID</th>
                                <th>Lines</th>
                                <th>Items</th>
                                <th>Submitted By</th>
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
                                <td><code>{{ $submission->request_data['trader_id'] ?? 'N/A' }}</code></td>
                                <td>{{ $submission->request_data['line_count'] ?? '-' }}</td>
                                <td>{{ $submission->request_data['item_count'] ?? '-' }}</td>
                                <td>{{ $submission->user?->name ?? 'System' }}</td>
                                <td>
                                    <a href="{{ route('ftp-submission.result', ['declaration' => $declaration, 'submission' => $submission]) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    @if($submission->can_retry)
                                        <form action="{{ route('ftp-submission.retry', $submission) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($submissions->hasPages())
                    <div class="card-footer">
                        {{ $submissions->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
