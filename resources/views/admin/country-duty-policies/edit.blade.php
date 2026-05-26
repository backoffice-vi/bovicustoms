@extends('layouts.app')

@section('title', 'Edit Duty Policy - Admin')

@section('content')
<div class="container-fluid py-4">
    <h2 class="mb-4"><i class="fas fa-edit me-2"></i>Edit Duty Policy</h2>

    @if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.country-duty-policies.update', $policy) }}">
                @method('PUT')
                @include('admin.country-duty-policies._form')
            </form>
        </div>
    </div>
</div>
@endsection
