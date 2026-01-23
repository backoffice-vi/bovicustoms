@extends('layouts.app')

@section('title', 'Country Documents - Admin')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-file-alt me-2"></i>Country Documents
        </h1>
        <div class="btn-group">
            <a href="{{ route('admin.country-documents.templates.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> Add Form Template
            </a>
            <a href="{{ route('admin.country-documents.support.create') }}" class="btn btn-outline-primary">
                <i class="fas fa-plus me-1"></i> Add Support Document
            </a>
        </div>
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

    <!-- Country Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.country-documents.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Filter by Country</label>
                    <select name="country_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Countries</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" {{ $selectedCountryId == $country->id ? 'selected' : '' }}>
                                {{ $country->flag_emoji }} {{ $country->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <div class="col-md-2">
                    @if($selectedCountryId)
                        <a href="{{ route('admin.country-documents.index', ['tab' => $activeTab]) }}" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Clear Filter
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="documentTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $activeTab === 'templates' ? 'active' : '' }}" 
               href="{{ route('admin.country-documents.index', ['tab' => 'templates', 'country_id' => $selectedCountryId]) }}">
                <i class="fas fa-file-invoice me-1"></i> Form Templates
                <span class="badge bg-secondary ms-1">{{ $templates->total() }}</span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $activeTab === 'support' ? 'active' : '' }}" 
               href="{{ route('admin.country-documents.index', ['tab' => 'support', 'country_id' => $selectedCountryId]) }}">
                <i class="fas fa-book me-1"></i> Support Documents
                <span class="badge bg-secondary ms-1">{{ $supportDocuments->total() }}</span>
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        @if($activeTab === 'templates')
            <!-- Form Templates Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Template</th>
                                <th>Country</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Status</th>
                                <th>Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($templates as $template)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas {{ $template->file_icon_class }} fa-lg me-2"></i>
                                            <div>
                                                <strong>{{ Str::limit($template->name, 35) }}</strong>
                                                <br>
                                                <small class="text-muted">{{ Str::limit($template->original_filename, 40) }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($template->country)
                                            {{ $template->country->flag_emoji }} {{ $template->country->name }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $template->form_type_label }}</span>
                                    </td>
                                    <td>{{ $template->formatted_file_size }}</td>
                                    <td>
                                        @if($template->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small>
                                            {{ $template->created_at->format('M d, Y') }}
                                            <br>
                                            <span class="text-muted">by {{ $template->uploader->name ?? 'Unknown' }}</span>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.country-documents.templates.show', $template) }}" class="btn btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.country-documents.templates.download', $template) }}" class="btn btn-outline-secondary" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.country-documents.templates.destroy', $template) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this template?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-file-invoice fa-3x mb-3 d-block"></i>
                                        <p class="mb-2">No form templates uploaded yet.</p>
                                        <a href="{{ route('admin.country-documents.templates.create') }}" class="btn btn-primary btn-sm mt-2">
                                            <i class="fas fa-plus me-1"></i> Upload First Template
                                        </a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($templates->hasPages())
                    <div class="card-footer">
                        {{ $templates->appends(['tab' => 'templates', 'country_id' => $selectedCountryId])->links() }}
                    </div>
                @endif
            </div>
        @else
            <!-- Support Documents Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Document</th>
                                <th>Country</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>AI Ready</th>
                                <th>Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($supportDocuments as $document)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas {{ $document->file_icon_class }} fa-lg me-2"></i>
                                            <div>
                                                <strong>{{ Str::limit($document->title, 35) }}</strong>
                                                <br>
                                                <small class="text-muted">{{ Str::limit($document->original_filename, 40) }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($document->country)
                                            {{ $document->country->flag_emoji }} {{ $document->country->name }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-info">{{ $document->document_type_label }}</span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $document->status_badge_class }}">
                                            {{ ucfirst($document->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($document->hasExtractedText() && $document->is_active)
                                            <span class="text-success"><i class="fas fa-check-circle"></i> Yes</span>
                                        @elseif($document->hasExtractedText() && !$document->is_active)
                                            <span class="text-warning"><i class="fas fa-pause-circle"></i> Inactive</span>
                                        @else
                                            <span class="text-muted"><i class="fas fa-times-circle"></i> No</span>
                                        @endif
                                    </td>
                                    <td>
                                        <small>
                                            {{ $document->created_at->format('M d, Y') }}
                                            <br>
                                            <span class="text-muted">by {{ $document->uploader->name ?? 'Unknown' }}</span>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.country-documents.support.show', $document) }}" class="btn btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.country-documents.support.download', $document) }}" class="btn btn-outline-secondary" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.country-documents.support.destroy', $document) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this document?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-book fa-3x mb-3 d-block"></i>
                                        <p class="mb-2">No support documents uploaded yet.</p>
                                        <a href="{{ route('admin.country-documents.support.create') }}" class="btn btn-primary btn-sm mt-2">
                                            <i class="fas fa-plus me-1"></i> Upload First Document
                                        </a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($supportDocuments->hasPages())
                    <div class="card-footer">
                        {{ $supportDocuments->appends(['tab' => 'support', 'country_id' => $selectedCountryId])->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Info Panel -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-file-invoice me-2 text-primary"></i>Form Templates</h5>
                    <p class="card-text mb-0">
                        Upload blank or sample declaration forms that BoVi Customs should prepare for users. 
                        These templates serve as the format reference for generated customs documents.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-book me-2 text-success"></i>Support Documents</h5>
                    <p class="card-text mb-0">
                        Upload government publications, tariff schedules, regulations, and guidelines.
                        The AI reads these documents to provide accurate customs information.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
