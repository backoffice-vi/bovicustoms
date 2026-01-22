@extends('layouts.app')

@section('title', 'Upload Law Document - Admin')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex align-items-center mb-4">
                <a href="{{ route('admin.law-documents.index') }}" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0">
                    <i class="fas fa-upload me-2"></i>Upload Law Document
                </h1>
            </div>

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.law-documents.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-4">
                            <label for="document" class="form-label">Document File <span class="text-danger">*</span></label>
                            <input type="file" 
                                   class="form-control @error('document') is-invalid @enderror" 
                                   id="document" 
                                   name="document"
                                   accept=".pdf,.doc,.docx,.txt,.xlsx,.xls"
                                   required>
                            <div class="form-text">
                                Supported formats: PDF, Word (.doc, .docx), Text (.txt), Excel (.xlsx, .xls). Max size: 50MB
                            </div>
                            @error('document')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="country_id" class="form-label">Country (Optional)</label>
                            <select class="form-select @error('country_id') is-invalid @enderror" 
                                    id="country_id" 
                                    name="country_id">
                                <option value="">All Countries (Global)</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}" {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                        {{ $country->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Select a country if this law document is country-specific, or leave blank for global application.
                            </div>
                            @error('country_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>How it works:</strong>
                            <ol class="mb-0 mt-2">
                                <li>Upload a law document containing tariff/customs code information</li>
                                <li>Click "Process Document" to extract categories using AI</li>
                                <li>AI will analyze the document and update the customs codes database</li>
                                <li>All changes are tracked in a history log</li>
                            </ol>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.law-documents.index') }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-1"></i> Upload Document
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
