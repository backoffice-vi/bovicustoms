@extends('layouts.app')

@section('title', $template->name . ' - Form Template')

@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('admin.country-documents.index', ['tab' => 'templates']) }}" class="btn btn-outline-secondary me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="h3 mb-0">
            <i class="fas {{ $template->file_icon_class }} me-2"></i>{{ $template->name }}
        </h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
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
            <!-- Template Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>Template Details
                    </h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Template Name</dt>
                        <dd class="col-sm-8">{{ $template->name }}</dd>

                        <dt class="col-sm-4">Country</dt>
                        <dd class="col-sm-8">
                            @if($template->country)
                                {{ $template->country->flag_emoji }} {{ $template->country->name }}
                            @else
                                <span class="text-muted">Not assigned</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4">Form Type</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-info">{{ $template->form_type_label }}</span>
                        </dd>

                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            @if($template->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </dd>

                        @if($template->description)
                            <dt class="col-sm-4">Description</dt>
                            <dd class="col-sm-8">{{ $template->description }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- File Information Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-file me-2"></i>File Information
                    </h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Original Filename</dt>
                        <dd class="col-sm-8">{{ $template->original_filename }}</dd>

                        <dt class="col-sm-4">File Type</dt>
                        <dd class="col-sm-8">{{ strtoupper($template->file_type) }}</dd>

                        <dt class="col-sm-4">File Size</dt>
                        <dd class="col-sm-8">{{ $template->formatted_file_size }}</dd>

                        <dt class="col-sm-4">Uploaded By</dt>
                        <dd class="col-sm-8">{{ $template->uploader->name ?? 'Unknown' }}</dd>

                        <dt class="col-sm-4">Uploaded On</dt>
                        <dd class="col-sm-8">{{ $template->created_at->format('F d, Y \a\t H:i') }}</dd>
                    </dl>
                </div>
            </div>
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
                        <a href="{{ route('admin.country-documents.templates.download', $template) }}" class="btn btn-primary">
                            <i class="fas fa-download me-1"></i> Download Template
                        </a>
                        
                        <form method="POST" action="{{ route('admin.country-documents.templates.toggle', $template) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn {{ $template->is_active ? 'btn-warning' : 'btn-success' }} w-100">
                                <i class="fas {{ $template->is_active ? 'fa-pause' : 'fa-play' }} me-1"></i>
                                {{ $template->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>

                        <hr>

                        <form method="POST" action="{{ route('admin.country-documents.templates.destroy', $template) }}" onsubmit="return confirm('Are you sure you want to delete this template? This action cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="fas fa-trash me-1"></i> Delete Template
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card bg-light">
                <div class="card-body text-center">
                    <i class="fas {{ $template->file_icon_class }} fa-3x mb-3"></i>
                    <h5 class="card-title">{{ strtoupper($template->file_type) }} Document</h5>
                    <p class="text-muted mb-0">{{ $template->formatted_file_size }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
