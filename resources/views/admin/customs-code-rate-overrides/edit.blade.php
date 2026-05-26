@extends('layouts.app')

@section('title', 'Edit Rate Override - Admin')

@section('content')
<div class="container-fluid py-4">
    <h2 class="mb-4"><i class="fas fa-edit me-2"></i>Edit Rate Override</h2>

    @if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.customs-code-rate-overrides.update', $override) }}">
                @method('PUT')
                @include('admin.customs-code-rate-overrides._form')
            </form>
        </div>
    </div>
</div>
@endsection
