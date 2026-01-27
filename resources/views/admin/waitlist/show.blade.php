@extends('layouts.app')

@section('title', 'Waitlist Signup Details - Admin')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-user me-2"></i>Waitlist Signup Details
        </h1>
        <a href="{{ route('admin.waitlist.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to List
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Signup Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th style="width: 200px;">Email</th>
                            <td>
                                <a href="mailto:{{ $signup->email }}">{{ $signup->email }}</a>
                            </td>
                        </tr>
                        <tr>
                            <th>Source</th>
                            <td>
                                @if($signup->source)
                                    <span class="badge bg-secondary">
                                        {{ ucwords(str_replace('_', ' ', $signup->source)) }}
                                    </span>
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Signed Up At</th>
                            <td>{{ $signup->created_at->format('F d, Y \a\t h:i A') }}</td>
                        </tr>
                        <tr>
                            <th>Time Since Signup</th>
                            <td>{{ $signup->created_at->diffForHumans() }}</td>
                        </tr>
                        <tr>
                            <th>Notified</th>
                            <td>
                                @if($signup->notified)
                                    <span class="badge bg-success">Yes</span>
                                @else
                                    <span class="badge bg-warning">Not yet</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            @if($signup->comments)
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-comment me-2"></i>Comments / Feedback</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">{{ $signup->comments }}</p>
                    </div>
                </div>
            @endif

            @if($signup->interested_features && count($signup->interested_features) > 0)
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-star me-2"></i>Interested Features</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            @foreach($signup->interested_features as $feature)
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>{{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Technical Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th>IP Address</th>
                            <td><code>{{ $signup->ip_address ?? 'Not recorded' }}</code></td>
                        </tr>
                        <tr>
                            <th>User Agent</th>
                            <td>
                                <small class="text-muted" style="word-break: break-all;">
                                    {{ $signup->user_agent ?? 'Not recorded' }}
                                </small>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Actions</h5>
                </div>
                <div class="card-body">
                    <a href="mailto:{{ $signup->email }}" class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-envelope me-1"></i> Send Email
                    </a>
                    <form method="POST" action="{{ route('admin.waitlist.destroy', $signup) }}" onsubmit="return confirm('Are you sure you want to remove this signup?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="fas fa-trash me-1"></i> Remove from Waitlist
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
