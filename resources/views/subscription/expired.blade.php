@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <div class="py-5">
                <i class="fas fa-exclamation-triangle text-warning" style="font-size: 5rem;"></i>
                <h1 class="mt-4 mb-3">Subscription Expired</h1>
                <p class="lead mb-4">Your subscription has expired. Please renew to continue using the platform.</p>
                <a href="{{ route('subscription.index') }}" class="btn btn-primary btn-lg">View Subscription Plans</a>
            </div>
        </div>
    </div>
</div>
@endsection
