@extends('layouts.app')

@section('title', $webFormTarget->name)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.web-form-targets.index') }}">Web Form Targets</a></li>
                    <li class="breadcrumb-item active">{{ $webFormTarget->name }}</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 mt-2">{{ $webFormTarget->name }}</h1>
        </div>
        <div class="btn-group">
            <a href="{{ route('admin.web-form-targets.edit', $webFormTarget) }}" class="btn btn-outline-primary">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <button type="button" class="btn btn-outline-info" id="test-connection-btn">
                <i class="bi bi-wifi"></i> Test Connection
            </button>
            <form action="{{ route('admin.web-form-targets.toggle-active', $webFormTarget) }}" method="POST" class="d-inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-outline-{{ $webFormTarget->is_active ? 'warning' : 'success' }}">
                    @if($webFormTarget->is_active)
                        <i class="bi bi-pause"></i> Deactivate
                    @else
                        <i class="bi bi-play"></i> Activate
                    @endif
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">Total Submissions</h6>
                    <h2 class="mb-0">{{ $stats['total_submissions'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">Successful</h6>
                    <h2 class="mb-0 text-success">{{ $stats['successful_submissions'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">Failed</h6>
                    <h2 class="mb-0 text-danger">{{ $stats['failed_submissions'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted">Field Mappings</h6>
                    <h2 class="mb-0">{{ $stats['total_fields'] }}</h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Target Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Target Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Code</dt>
                                <dd class="col-sm-8"><code>{{ $webFormTarget->code }}</code></dd>

                                <dt class="col-sm-4">Country</dt>
                                <dd class="col-sm-8">{{ $webFormTarget->country->name ?? '-' }}</dd>

                                <dt class="col-sm-4">Base URL</dt>
                                <dd class="col-sm-8">
                                    <a href="{{ $webFormTarget->base_url }}" target="_blank">
                                        {{ $webFormTarget->base_url }}
                                        <i class="bi bi-box-arrow-up-right small"></i>
                                    </a>
                                </dd>

                                <dt class="col-sm-4">Login URL</dt>
                                <dd class="col-sm-8">
                                    <a href="{{ $webFormTarget->full_login_url }}" target="_blank">
                                        {{ $webFormTarget->login_url }}
                                        <i class="bi bi-box-arrow-up-right small"></i>
                                    </a>
                                </dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Auth Type</dt>
                                <dd class="col-sm-8">{{ ucfirst($webFormTarget->auth_type) }}</dd>

                                <dt class="col-sm-4">AI Mode</dt>
                                <dd class="col-sm-8">
                                    @if($webFormTarget->requires_ai)
                                        <span class="badge bg-info">Enabled</span>
                                    @else
                                        <span class="badge bg-secondary">Disabled</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-4">Last Tested</dt>
                                <dd class="col-sm-8">
                                    {{ $webFormTarget->last_tested_at ? $webFormTarget->last_tested_at->format('M j, Y g:ia') : 'Never' }}
                                </dd>

                                <dt class="col-sm-4">Last Mapped</dt>
                                <dd class="col-sm-8">
                                    {{ $webFormTarget->last_mapped_at ? $webFormTarget->last_mapped_at->format('M j, Y g:ia') : 'Never' }}
                                </dd>
                            </dl>
                        </div>
                    </div>

                    @if($webFormTarget->notes)
                        <hr>
                        <h6>Notes</h6>
                        <p class="text-muted mb-0">{{ $webFormTarget->notes }}</p>
                    @endif
                </div>
            </div>

            <!-- Pages -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Pages ({{ $webFormTarget->pages->count() }})</h5>
                    <a href="{{ route('admin.web-form-targets.pages.create', $webFormTarget) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus"></i> Add Page
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($webFormTarget->pages->isEmpty())
                        <div class="text-center py-4">
                            <p class="text-muted mb-2">No pages configured yet.</p>
                            <a href="{{ route('admin.web-form-targets.pages.create', $webFormTarget) }}" class="btn btn-outline-primary btn-sm">
                                Add First Page
                            </a>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>URL Pattern</th>
                                        <th>Fields</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($webFormTarget->pages->sortBy('sequence_order') as $page)
                                        <tr>
                                            <td>{{ $page->sequence_order }}</td>
                                            <td>
                                                <a href="{{ route('admin.web-form-targets.pages.show', [$webFormTarget, $page]) }}">
                                                    {{ $page->name }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $page->page_type === 'login' ? 'warning' : ($page->page_type === 'form' ? 'primary' : 'secondary') }}">
                                                    {{ $page->page_type_label }}
                                                </span>
                                            </td>
                                            <td><code>{{ Str::limit($page->url_pattern, 30) }}</code></td>
                                            <td>{{ $page->fieldMappings->count() }}</td>
                                            <td>
                                                @if($page->is_active)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-secondary">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('admin.web-form-targets.pages.show', [$webFormTarget, $page]) }}" 
                                                       class="btn btn-outline-primary" title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="{{ route('admin.web-form-targets.pages.edit', [$webFormTarget, $page]) }}" 
                                                       class="btn btn-outline-secondary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
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

            <!-- Recent Submissions -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Submissions</h5>
                    <a href="{{ route('admin.web-form-targets.submissions', $webFormTarget) }}" class="btn btn-sm btn-outline-secondary">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($webFormTarget->submissions->isEmpty())
                        <div class="text-center py-4">
                            <p class="text-muted mb-0">No submissions yet.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Declaration</th>
                                        <th>Status</th>
                                        <th>Reference</th>
                                        <th>Duration</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($webFormTarget->submissions as $submission)
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.web-form-targets.submissions.show', [$webFormTarget, $submission]) }}">
                                                    {{ $submission->declaration->form_number ?? '#' . $submission->declaration_form_id }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $submission->status_color }}">
                                                    {{ $submission->status_label }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($submission->external_reference)
                                                    <code>{{ $submission->external_reference }}</code>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $submission->formatted_duration }}</td>
                                            <td>{{ $submission->created_at->format('M j, Y g:ia') }}</td>
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
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.web-form-targets.pages.create', $webFormTarget) }}" class="btn btn-outline-primary">
                            <i class="bi bi-plus-lg"></i> Add Page
                        </a>
                        <a href="{{ route('admin.web-form-targets.submissions', $webFormTarget) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-list-ul"></i> View All Submissions
                        </a>
                        <button type="button" class="btn btn-outline-info" id="test-connection-btn-2">
                            <i class="bi bi-wifi"></i> Test Connection
                        </button>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Status</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex justify-content-between align-items-center mb-2">
                            <span>Active</span>
                            @if($webFormTarget->is_active)
                                <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                                <i class="bi bi-x-circle-fill text-danger"></i>
                            @endif
                        </li>
                        <li class="d-flex justify-content-between align-items-center mb-2">
                            <span>Has Pages</span>
                            @if($webFormTarget->pages->count() > 0)
                                <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                                <i class="bi bi-x-circle-fill text-danger"></i>
                            @endif
                        </li>
                        <li class="d-flex justify-content-between align-items-center mb-2">
                            <span>Has Mappings</span>
                            @if($stats['total_fields'] > 0)
                                <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                                <i class="bi bi-x-circle-fill text-warning"></i>
                            @endif
                        </li>
                        <li class="d-flex justify-content-between align-items-center">
                            <span>Tested</span>
                            @if($webFormTarget->last_tested_at)
                                <i class="bi bi-check-circle-fill text-success"></i>
                            @else
                                <i class="bi bi-question-circle-fill text-warning"></i>
                            @endif
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Credentials (masked) -->
            @if($webFormTarget->auth_type === 'form')
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Authentication</h5>
                    </div>
                    <div class="card-body">
                        @php $creds = $webFormTarget->decrypted_credentials; @endphp
                        @if($creds)
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Username</dt>
                                <dd class="col-sm-8">{{ $creds['username'] ?? '-' }}</dd>

                                <dt class="col-sm-4">Password</dt>
                                <dd class="col-sm-8">********</dd>
                            </dl>
                        @else
                            <p class="text-muted mb-0">No credentials configured.</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('#test-connection-btn, #test-connection-btn-2').forEach(btn => {
    btn.addEventListener('click', async function() {
        const icon = this.querySelector('i');
        const originalClass = icon.className;
        
        icon.className = 'bi bi-arrow-repeat spin';
        this.disabled = true;
        
        try {
            const response = await fetch('{{ route('admin.web-form-targets.test', $webFormTarget) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Connection successful!');
                location.reload();
            } else {
                alert('Connection failed: ' + data.message);
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
        
        icon.className = originalClass;
        this.disabled = false;
    });
});
</script>
<style>
.spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
@endpush
@endsection
