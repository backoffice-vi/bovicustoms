@extends('layouts.app')

@section('title', 'View User - Admin')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>User Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Name</label>
                        <p class="mb-0"><strong>{{ $user->name }}</strong></p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Email</label>
                        <p class="mb-0">{{ $user->email }}</p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Role</label>
                        <p class="mb-0">
                            @if($user->role === 'admin')
                                <span class="badge bg-danger">Admin</span>
                            @else
                                <span class="badge bg-secondary">User</span>
                            @endif
                        </p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Account Type</label>
                        <p class="mb-0">
                            @if($user->is_individual)
                                <span class="badge bg-info">Individual</span>
                            @else
                                <span class="badge bg-success">Organization</span>
                            @endif
                        </p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Organization</label>
                        <p class="mb-0">
                            @if($user->organization)
                                <i class="fas fa-building me-1"></i>{{ $user->organization->name }}
                            @else
                                <span class="text-muted">None</span>
                            @endif
                        </p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Onboarding</label>
                        <p class="mb-0">
                            @if($user->onboarding_completed)
                                <span class="badge bg-success">Completed</span>
                            @else
                                <span class="badge bg-warning">Pending</span>
                            @endif
                        </p>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small">Created</label>
                        <p class="mb-0">{{ $user->created_at->format('M d, Y h:i A') }}</p>
                    </div>

                    <div class="mb-0">
                        <label class="text-muted small">Last Updated</label>
                        <p class="mb-0">{{ $user->updated_at->format('M d, Y h:i A') }}</p>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary btn-sm w-100 mb-2">
                        <i class="fas fa-edit me-1"></i> Edit User
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="fas fa-arrow-left me-1"></i> Back to Users
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Recent Invoices</h5>
                </div>
                <div class="card-body">
                    @if($user->invoices->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Status</th>
                                        <th>Items</th>
                                        <th>Total Value</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($user->invoices as $invoice)
                                        <tr>
                                            <td><strong>{{ $invoice->invoice_number }}</strong></td>
                                            <td>
                                                @if($invoice->status === 'draft')
                                                    <span class="badge bg-secondary">Draft</span>
                                                @elseif($invoice->status === 'pending_classification')
                                                    <span class="badge bg-warning">Pending</span>
                                                @elseif($invoice->status === 'classified')
                                                    <span class="badge bg-info">Classified</span>
                                                @else
                                                    <span class="badge bg-success">Complete</span>
                                                @endif
                                            </td>
                                            <td>{{ $invoice->items->count() }}</td>
                                            <td>${{ number_format($invoice->total_value, 2) }}</td>
                                            <td>{{ $invoice->created_at->format('M d, Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-file-invoice fa-3x mb-3 d-block"></i>
                            <p>No invoices yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
