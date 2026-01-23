@extends('layouts.app')

@section('title', $document->title . ' - Support Document')

@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('admin.country-documents.index', ['tab' => 'support']) }}" class="btn btn-outline-secondary me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="h3 mb-0">
            <i class="fas {{ $document->file_icon_class }} me-2"></i>{{ $document->title }}
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
        <div class="col-md-8">
            <!-- Document Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>Document Details
                    </h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Title</dt>
                        <dd class="col-sm-8">{{ $document->title }}</dd>

                        <dt class="col-sm-4">Country</dt>
                        <dd class="col-sm-8">
                            @if($document->country)
                                {{ $document->country->flag_emoji }} {{ $document->country->name }}
                            @else
                                <span class="text-muted">Not assigned</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Document Type</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-info">{{ $document->document_type_label }}</span>
                        </dd>

                        <dt class="col-sm-4">Processing Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge {{ $document->status_badge_class }}">
                                {{ ucfirst($document->status) }}
                            </span>
                        </dd>

                        <dt class="col-sm-4">Active Status</dt>
                        <dd class="col-sm-8">
                            @if($document->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </dd>

                        @if($document->description)
                            <dt class="col-sm-4">Description</dt>
                            <dd class="col-sm-8">{{ $document->description }}</dd>
                        @endif

                        @if($document->error_message)
                            <dt class="col-sm-4">Error Message</dt>
                            <dd class="col-sm-8">
                                <span class="text-danger">{{ $document->error_message }}</span>
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- File Information Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-file me-2"></i>File Information
                    </h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Original Filename</dt>
                        <dd class="col-sm-8">{{ $document->original_filename }}</dd>

                        <dt class="col-sm-4">File Type</dt>
                        <dd class="col-sm-8">{{ strtoupper($document->file_type) }}</dd>

                        <dt class="col-sm-4">File Size</dt>
                        <dd class="col-sm-8">{{ $document->formatted_file_size }}</dd>

                        <dt class="col-sm-4">Uploaded By</dt>
                        <dd class="col-sm-8">{{ $document->uploader->name ?? 'Unknown' }}</dd>

                        <dt class="col-sm-4">Uploaded On</dt>
                        <dd class="col-sm-8">{{ $document->created_at->format('F d, Y \a\t H:i') }}</dd>

                        @if($document->extracted_at)
                            <dt class="col-sm-4">Text Extracted On</dt>
                            <dd class="col-sm-8">{{ $document->extracted_at->format('F d, Y \a\t H:i') }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Extracted Text Card -->
            @if($document->hasExtractedText())
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-file-alt me-2"></i>Extracted Text
                        </h5>
                        <span class="badge bg-success">{{ number_format(strlen($document->extracted_text)) }} characters</span>
                    </div>
                    <div class="card-body">
                        <div class="bg-light p-3 rounded" style="max-height: 500px; overflow-y: auto;">
                            <pre class="mb-0" style="white-space: pre-wrap; word-wrap: break-word; font-size: 0.85rem;">{{ Str::limit($document->extracted_text, 10000) }}</pre>
                            @if(strlen($document->extracted_text) > 10000)
                                <div class="text-center mt-3">
                                    <span class="text-muted">... text truncated for display ({{ number_format(strlen($document->extracted_text) - 10000) }} more characters) ...</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @elseif($document->isPending() || $document->isFailed())
                <div class="card bg-light">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-file-import fa-3x text-muted mb-3"></i>
                        <h5>Text Not Yet Extracted</h5>
                        <p class="text-muted mb-3">
                            Click "Extract Text" to process this document so the AI can read its contents.
                        </p>
                        <form method="POST" action="{{ route('admin.country-documents.support.extract', $document) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-magic me-1"></i> Extract Text Now
                            </button>
                        </form>
                    </div>
                </div>
            @elseif($document->isProcessing())
                <div class="card bg-light">
                    <div class="card-body text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Processing...</span>
                        </div>
                        <h5>Processing Document</h5>
                        <p class="text-muted mb-0">
                            Please wait while we extract text from this document...
                        </p>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-4">
            <!-- Actions Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-cog me-2"></i>Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.country-documents.support.download', $document) }}" class="btn btn-primary">
                            <i class="fas fa-download me-1"></i> Download Document
                        </a>

                        @if($document->isPending() || $document->isFailed())
                            <form method="POST" action="{{ route('admin.country-documents.support.extract', $document) }}">
                                @csrf
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-magic me-1"></i> Extract Text
                                </button>
                            </form>
                        @elseif($document->isCompleted())
                            <form method="POST" action="{{ route('admin.country-documents.support.extract', $document) }}">
                                @csrf
                                <button type="submit" class="btn btn-outline-success w-100">
                                    <i class="fas fa-sync me-1"></i> Re-extract Text
                                </button>
                            </form>
                        @endif
                        
                        <form method="POST" action="{{ route('admin.country-documents.support.toggle', $document) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn {{ $document->is_active ? 'btn-warning' : 'btn-success' }} w-100">
                                <i class="fas {{ $document->is_active ? 'fa-pause' : 'fa-play' }} me-1"></i>
                                {{ $document->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>

                        <hr>

                        <form method="POST" action="{{ route('admin.country-documents.support.destroy', $document) }}" onsubmit="return confirm('Are you sure you want to delete this document? This action cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="fas fa-trash me-1"></i> Delete Document
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- AI Status Card -->
            <div class="card {{ $document->hasExtractedText() && $document->is_active ? 'bg-success text-white' : 'bg-light' }}">
                <div class="card-body text-center">
                    @if($document->hasExtractedText() && $document->is_active)
                        <i class="fas fa-robot fa-3x mb-3"></i>
                        <h5 class="card-title">AI Ready</h5>
                        <p class="mb-0">
                            This document is available for AI reference when preparing customs forms.
                        </p>
                    @elseif($document->hasExtractedText() && !$document->is_active)
                        <i class="fas fa-pause-circle fa-3x mb-3 text-warning"></i>
                        <h5 class="card-title">Inactive</h5>
                        <p class="text-muted mb-0">
                            Text is extracted but the document is currently inactive.
                        </p>
                    @else
                        <i class="fas fa-robot fa-3x mb-3 text-muted"></i>
                        <h5 class="card-title">Not Ready</h5>
                        <p class="text-muted mb-0">
                            Extract text to make this document available for AI reference.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
