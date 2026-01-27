@extends('layouts.app')

@section('title', $page->name . ' - ' . $webFormTarget->name)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.web-form-targets.index') }}">Web Form Targets</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.web-form-targets.show', $webFormTarget) }}">{{ $webFormTarget->name }}</a></li>
                    <li class="breadcrumb-item active">{{ $page->name }}</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 mt-2">{{ $page->name }}</h1>
        </div>
        <div class="btn-group">
            <a href="{{ route('admin.web-form-targets.pages.edit', [$webFormTarget, $page]) }}" class="btn btn-outline-primary">
                <i class="bi bi-pencil"></i> Edit Page
            </a>
            <a href="{{ route('admin.web-form-targets.mappings.create', [$webFormTarget, $page]) }}" class="btn btn-primary">
                <i class="bi bi-plus"></i> Add Field Mapping
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <!-- Page Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Page Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Type</dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-{{ $page->page_type === 'login' ? 'warning' : ($page->page_type === 'form' ? 'primary' : 'secondary') }}">
                                        {{ $page->page_type_label }}
                                    </span>
                                </dd>

                                <dt class="col-sm-4">URL</dt>
                                <dd class="col-sm-8">
                                    <a href="{{ $page->full_url }}" target="_blank">
                                        {{ $page->url_pattern }}
                                        <i class="bi bi-box-arrow-up-right small"></i>
                                    </a>
                                </dd>

                                <dt class="col-sm-4">Sequence</dt>
                                <dd class="col-sm-8">{{ $page->sequence_order }}</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Submit</dt>
                                <dd class="col-sm-8"><code>{{ $page->submit_selector ?: '-' }}</code></dd>

                                <dt class="col-sm-4">Success</dt>
                                <dd class="col-sm-8"><code>{{ $page->success_indicator ?: '-' }}</code></dd>

                                <dt class="col-sm-4">Error</dt>
                                <dd class="col-sm-8"><code>{{ $page->error_indicator ?: '-' }}</code></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Field Mappings -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Field Mappings ({{ $page->fieldMappings->count() }})</h5>
                    <a href="{{ route('admin.web-form-targets.mappings.create', [$webFormTarget, $page]) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus"></i> Add Mapping
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($page->fieldMappings->isEmpty())
                        <div class="text-center py-5">
                            <i class="bi bi-arrows-angle-contract display-1 text-muted"></i>
                            <p class="text-muted mt-3">No field mappings configured yet.</p>
                            <a href="{{ route('admin.web-form-targets.mappings.create', [$webFormTarget, $page]) }}" class="btn btn-primary">
                                Add First Field Mapping
                            </a>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Web Field</th>
                                        <th>Type</th>
                                        <th>Local Source</th>
                                        <th>Required</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($page->fieldMappings->sortBy('tab_order') as $mapping)
                                        <tr>
                                            <td>{{ $mapping->tab_order ?? '-' }}</td>
                                            <td>
                                                <strong>{{ $mapping->web_field_label }}</strong>
                                                @if($mapping->section)
                                                    <br><small class="text-muted">{{ $mapping->section }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $mapping->field_type_label }}</span>
                                            </td>
                                            <td>
                                                @if($mapping->static_value)
                                                    <span class="text-info" title="Static value">
                                                        <i class="bi bi-lock"></i> "{{ Str::limit($mapping->static_value, 20) }}"
                                                    </span>
                                                @elseif($mapping->local_field)
                                                    <code>{{ $mapping->local_field }}</code>
                                                @elseif($mapping->default_value)
                                                    <span class="text-muted">Default: {{ Str::limit($mapping->default_value, 20) }}</span>
                                                @else
                                                    <span class="text-warning">
                                                        <i class="bi bi-exclamation-triangle"></i> Not mapped
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($mapping->is_required)
                                                    <span class="badge bg-danger">Required</span>
                                                @else
                                                    <span class="text-muted">Optional</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($mapping->is_active)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-secondary">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('admin.web-form-targets.mappings.edit', [$webFormTarget, $page, $mapping]) }}" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form action="{{ route('admin.web-form-targets.mappings.destroy', [$webFormTarget, $page, $mapping]) }}" 
                                                          method="POST" class="d-inline"
                                                          onsubmit="return confirm('Delete this field mapping?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Page Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Status</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex justify-content-between align-items-center mb-2">
                            <span>Active</span>
                            @if($page->is_active)
                                <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                                <i class="bi bi-x-circle-fill text-danger"></i>
                            @endif
                        </li>
                        <li class="d-flex justify-content-between align-items-center mb-2">
                            <span>Has Mappings</span>
                            @if($page->fieldMappings->count() > 0)
                                <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                                <i class="bi bi-x-circle-fill text-warning"></i>
                            @endif
                        </li>
                        <li class="d-flex justify-content-between align-items-center">
                            <span>Required Fields Mapped</span>
                            @if(!$page->hasUnmappedRequiredFields())
                                <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                                <i class="bi bi-exclamation-circle-fill text-danger"></i>
                            @endif
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Mapping Stats</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <h4 class="mb-0">{{ $page->fieldMappings->count() }}</h4>
                            <small class="text-muted">Total</small>
                        </div>
                        <div class="col-4">
                            <h4 class="mb-0 text-danger">{{ $page->fieldMappings->where('is_required', true)->count() }}</h4>
                            <small class="text-muted">Required</small>
                        </div>
                        <div class="col-4">
                            <h4 class="mb-0 text-success">{{ $page->fieldMappings->where('is_active', true)->count() }}</h4>
                            <small class="text-muted">Active</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.web-form-targets.mappings.create', [$webFormTarget, $page]) }}" class="btn btn-primary">
                            <i class="bi bi-plus"></i> Add Field Mapping
                        </a>
                        <a href="{{ route('admin.web-form-targets.pages.edit', [$webFormTarget, $page]) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-pencil"></i> Edit Page
                        </a>
                        <a href="{{ $page->full_url }}" target="_blank" class="btn btn-outline-info">
                            <i class="bi bi-box-arrow-up-right"></i> Open Page
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
