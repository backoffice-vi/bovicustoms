@extends('layouts.app')

@section('title', 'Edit Trade Contact - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-user-edit me-2"></i>Edit Trade Contact</h2>
                <a href="{{ route('trade-contacts.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </a>
            </div>

            <div class="card">
                <div class="card-body">
                    <form action="{{ route('trade-contacts.update', $tradeContact) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        @include('trade-contacts._form', ['contact' => $tradeContact])
                        
                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                            <a href="{{ route('trade-contacts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Contact
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
