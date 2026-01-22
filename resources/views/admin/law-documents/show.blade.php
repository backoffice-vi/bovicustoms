@extends('layouts.app')

@section('title', 'View Law Document - Admin')

@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('admin.law-documents.index') }}" class="btn btn-outline-secondary me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="h3 mb-0">
            <i class="fas fa-file-alt me-2"></i>Document Details
        </h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <!-- Document Info -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Document Information</h5>
                    <span class="badge {{ $lawDocument->status_badge_class }} fs-6">
                        {{ ucfirst($lawDocument->status) }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4 text-muted">Filename</div>
                        <div class="col-md-8">
                            <strong>{{ $lawDocument->original_filename }}</strong>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 text-muted">File Type</div>
                        <div class="col-md-8">{{ strtoupper($lawDocument->file_type) }}</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 text-muted">File Size</div>
                        <div class="col-md-8">{{ $lawDocument->formatted_file_size }}</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 text-muted">Country</div>
                        <div class="col-md-8">
                            @if($lawDocument->country)
                                {{ $lawDocument->country->name }}
                            @else
                                <span class="text-muted">All Countries (Global)</span>
                            @endif
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 text-muted">Uploaded By</div>
                        <div class="col-md-8">{{ $lawDocument->uploader->name ?? 'Unknown' }}</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4 text-muted">Uploaded At</div>
                        <div class="col-md-8">{{ $lawDocument->created_at->format('F d, Y \a\t h:i A') }}</div>
                    </div>
                    @if($lawDocument->processed_at)
                        <div class="row mb-3">
                            <div class="col-md-4 text-muted">Processed At</div>
                            <div class="col-md-8">{{ $lawDocument->processed_at->format('F d, Y \a\t h:i A') }}</div>
                        </div>
                    @endif
                    @if($lawDocument->error_message)
                        <div class="row mb-3">
                            <div class="col-md-4 text-muted">Error</div>
                            <div class="col-md-8">
                                <div class="alert alert-danger mb-0 py-2">
                                    {{ $lawDocument->error_message }}
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- History Entries -->
            @if($historyEntries->count() > 0)
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Changes Made ({{ $historyEntries->count() }})
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Customs Code</th>
                                    <th>Change</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($historyEntries as $entry)
                                    <tr>
                                        <td>
                                            <strong>{{ $entry->customsCode->code ?? 'N/A' }}</strong>
                                        </td>
                                        <td>
                                            <small>{{ $entry->change_description }}</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $entry->created_at->format('M d, H:i') }}</small>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <!-- Actions Sidebar -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <!-- Process Button -->
                        @if($lawDocument->isPending() || $lawDocument->isFailed())
                            <form method="POST" action="{{ route('admin.law-documents.process', $lawDocument) }}">
                                @csrf
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-robot me-1"></i> Process Document
                                </button>
                            </form>
                        @elseif($lawDocument->isProcessing())
                            <button class="btn btn-info w-100" disabled>
                                <i class="fas fa-spinner fa-spin me-1"></i> Processing...
                            </button>
                        @elseif($lawDocument->isCompleted())
                            <form method="POST" action="{{ route('admin.law-documents.reprocess', $lawDocument) }}" 
                                  onsubmit="return confirm('This will re-process the document. Existing codes will be updated if changes are found. Continue?');">
                                @csrf
                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="fas fa-redo me-1"></i> Reprocess Document
                                </button>
                            </form>
                        @endif

                        <!-- Download -->
                        <a href="{{ route('admin.law-documents.download', $lawDocument) }}" class="btn btn-outline-primary">
                            <i class="fas fa-download me-1"></i> Download Original
                        </a>

                        <!-- Delete -->
                        <form method="POST" action="{{ route('admin.law-documents.destroy', $lawDocument) }}"
                              onsubmit="return confirm('Are you sure you want to delete this document? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="fas fa-trash me-1"></i> Delete Document
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Processing Info -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Processing</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-2">
                        When you process this document, AI will:
                    </p>
                    <ul class="text-muted small mb-0">
                        <li>Extract text from the document</li>
                        <li>Identify tariff codes and descriptions</li>
                        <li>Update or create customs codes</li>
                        <li>Log all changes for audit</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
