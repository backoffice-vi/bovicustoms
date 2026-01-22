@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-4">Subscription Management</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($organization)
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Current Plan</h5>
                        <p class="display-6 text-primary">{{ ucfirst($organization->subscription_plan) }}</p>
                        <p class="text-muted">Status: <span class="badge bg-{{ $organization->subscription_status === 'active' ? 'success' : ($organization->subscription_status === 'trial' ? 'info' : 'warning') }}">
                            {{ ucfirst($organization->subscription_status) }}
                        </span></p>
                        
                        @if($organization->isOnTrial())
                            <p class="text-muted">Trial ends: {{ $organization->trial_ends_at->format('F j, Y') }}</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Usage This Month</h5>
                        <p class="display-6">
                            @php
                                $monthStart = now()->startOfMonth();
                                $used = $organization->invoices()->where('created_at', '>=', $monthStart)->count();
                                $limit = $organization->invoice_limit;
                            @endphp
                            {{ $used }} / {{ $limit ? $limit : '∞' }}
                        </p>
                        <p class="text-muted">Invoices processed</p>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info">
            <strong>Individual Account:</strong> You're on a free individual account with 10 invoices per month limit.
            Create an organization to access premium features and team collaboration.
        </div>
    @endif

    <h2 class="mb-4">Available Plans</h2>
    <div class="row g-4">
        @foreach($plans as $plan)
            <div class="col-md-4">
                <div class="card {{ $organization && $organization->subscription_plan === $plan->slug ? 'border-primary' : '' }}">
                    <div class="card-body">
                        @if($organization && $organization->subscription_plan === $plan->slug)
                            <span class="badge bg-primary mb-2">Current Plan</span>
                        @endif
                        <h3>{{ $plan->name }}</h3>
                        <p class="display-4">${{ number_format($plan->price, 0) }}<small class="text-muted">/mo</small></p>
                        <ul class="list-unstyled mb-4">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> 
                                {{ $plan->invoice_limit ? $plan->invoice_limit . ' invoices/month' : 'Unlimited invoices' }}
                            </li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> 
                                {{ $plan->country_limit ? 'Up to ' . $plan->country_limit . ' countries' : 'All countries' }}
                            </li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i> 
                                {{ $plan->team_member_limit ? 'Up to ' . $plan->team_member_limit . ' team members' : 'Unlimited team members' }}
                            </li>
                            @if($plan->features)
                                @foreach($plan->features as $feature)
                                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> {{ $feature }}</li>
                                @endforeach
                            @endif
                        </ul>
                        @if($organization && $organization->subscription_plan !== $plan->slug)
                            <form method="POST" action="{{ route('subscription.upgrade') }}">
                                @csrf
                                <input type="hidden" name="plan_slug" value="{{ $plan->slug }}">
                                <button type="submit" class="btn btn-primary w-100">Upgrade to {{ $plan->name }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if(!$organization)
        <div class="mt-5 text-center">
            <p class="text-muted">Want to unlock premium features?</p>
            <a href="#" class="btn btn-primary">Create an Organization</a>
        </div>
    @endif
</div>
@endsection
