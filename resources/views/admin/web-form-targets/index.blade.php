@extends('layouts.app')

@section('title', 'Web Form Targets')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Web Form Targets</h1>
        <a href="{{ route('admin.web-form-targets.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Add Target
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Targets</h6>
                    <h2 class="mb-0">{{ $stats['total_targets'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Active Targets</h6>
                    <h2 class="mb-0">{{ $stats['active_targets'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Submissions</h6>
                    <h2 class="mb-0">{{ $stats['total_submissions'] }}</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h6 class="card-title">Successful</h6>
                    <h2 class="mb-0">{{ $stats['successful_submissions'] }}</h2>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Targets Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Configured Targets</h5>
        </div>
        <div class="card-body p-0">
            @if($targets->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-globe display-1 text-muted"></i>
                    <p class="text-muted mt-3">No web form targets configured yet.</p>
                    <a href="{{ route('admin.web-form-targets.create') }}" class="btn btn-primary">
                        Add Your First Target
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Country</th>
                                <th>Base URL</th>
                                <th>Pages</th>
                                <th>Submissions</th>
                                <th>Status</th>
                                <th>Last Tested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($targets as $target)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.web-form-targets.show', $target) }}" class="fw-semibold text-decoration-none">
                                            {{ $target->name }}
                                        </a>
                                        <br>
                                        <small class="text-muted">{{ $target->code }}</small>
                                    </td>
                                    <td>{{ $target->country->name ?? '-' }}</td>
                                    <td>
                                        <a href="{{ $target->base_url }}" target="_blank" class="text-decoration-none">
                                            {{ Str::limit($target->base_url, 40) }}
                                            <i class="bi bi-box-arrow-up-right small"></i>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $target->pages_count }} pages</span>
                                    </td>
                                    <td>{{ $target->submissions_count }}</td>
                                    <td>
                                        @if($target->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                        @if($target->requires_ai)
                                            <span class="badge bg-info" title="Uses AI assistance">AI</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($target->last_tested_at)
                                            {{ $target->last_tested_at->diffForHumans() }}
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('admin.web-form-targets.show', $target) }}" class="btn btn-outline-primary" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.web-form-targets.edit', $target) }}" class="btn btn-outline-secondary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-info test-connection-btn" 
                                                    data-target-id="{{ $target->id }}" title="Test Connection">
                                                <i class="bi bi-wifi"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if($targets->hasPages())
            <div class="card-footer">
                {{ $targets->links() }}
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.test-connection-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const targetId = this.dataset.targetId;
        const icon = this.querySelector('i');
        
        icon.className = 'bi bi-arrow-repeat spin';
        this.disabled = true;
        
        try {
            const response = await fetch(`/admin/web-form-targets/${targetId}/test`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                icon.className = 'bi bi-check-circle text-success';
                alert('Connection successful!');
            } else {
                icon.className = 'bi bi-x-circle text-danger';
                alert('Connection failed: ' + data.message);
            }
        } catch (error) {
            icon.className = 'bi bi-x-circle text-danger';
            alert('Error: ' + error.message);
        }
        
        this.disabled = false;
        setTimeout(() => {
            icon.className = 'bi bi-wifi';
        }, 3000);
    });
});
</script>
<style>
.spin {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
@endpush
@endsection
