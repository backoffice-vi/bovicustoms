@extends('layouts.app')

@section('title', 'Law Documents - Admin')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-gavel me-2"></i>Law Documents
        </h1>
        <a href="{{ route('admin.law-documents.create') }}" class="btn btn-primary">
            <i class="fas fa-upload me-1"></i> Upload Document
        </a>
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

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.law-documents.index') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Processing</option>
                        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Country</label>
                    <select name="country_id" class="form-select">
                        <option value="">All Countries</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" {{ request('country_id') == $country->id ? 'selected' : '' }}>
                                {{ $country->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-secondary me-2">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <a href="{{ route('admin.law-documents.index') }}" class="btn btn-outline-secondary">
                        Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Documents Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Document</th>
                        <th>Country</th>
                        <th>Status</th>
                        <th>Size</th>
                        <th>Uploaded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $document)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-file-{{ $document->file_type === 'pdf' ? 'pdf text-danger' : ($document->file_type === 'docx' || $document->file_type === 'doc' ? 'word text-primary' : 'alt text-secondary') }} fa-lg me-2"></i>
                                    <div>
                                        <strong>{{ Str::limit($document->original_filename, 40) }}</strong>
                                        <br>
                                        <small class="text-muted">{{ strtoupper($document->file_type) }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($document->country)
                                    {{ $document->country->name }}
                                @else
                                    <span class="text-muted">All Countries</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $document->status_badge_class }}">
                                    {{ ucfirst($document->status) }}
                                </span>
                            </td>
                            <td>{{ $document->formatted_file_size }}</td>
                            <td>
                                <small>
                                    {{ $document->created_at->format('M d, Y') }}
                                    <br>
                                    <span class="text-muted">by {{ $document->uploader->name ?? 'Unknown' }}</span>
                                </small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.law-documents.show', $document) }}" class="btn btn-outline-primary" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.law-documents.download', $document) }}" class="btn btn-outline-secondary" title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.law-documents.destroy', $document) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this document?');">
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
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-folder-open fa-2x mb-2"></i>
                                <p class="mb-0">No documents uploaded yet.</p>
                                <a href="{{ route('admin.law-documents.create') }}" class="btn btn-primary btn-sm mt-2">
                                    Upload First Document
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($documents->hasPages())
            <div class="card-footer">
                {{ $documents->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
