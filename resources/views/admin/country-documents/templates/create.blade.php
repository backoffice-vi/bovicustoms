@extends('layouts.app')

@section('title', 'Upload Form Template - Admin')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex align-items-center mb-4">
                <a href="{{ route('admin.country-documents.index', ['tab' => 'templates']) }}" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0">
                    <i class="fas fa-file-invoice me-2"></i>Upload Form Template
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
                    <form method="POST" action="{{ route('admin.country-documents.templates.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-4">
                            <label for="country_id" class="form-label">Country <span class="text-danger">*</span></label>
                            <select class="form-select @error('country_id') is-invalid @enderror" 
                                    id="country_id" 
                                    name="country_id"
                                    required>
                                <option value="">Select a country...</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}" {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                        {{ $country->flag_emoji }} {{ $country->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Select the country this form template is for.
                            </div>
                            @error('country_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="name" class="form-label">Template Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   id="name" 
                                   name="name"
                                   value="{{ old('name') }}"
                                   placeholder="e.g., C41 Import Declaration Form"
                                   required>
                            <div class="form-text">
                                A descriptive name for this form template.
                            </div>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="form_type" class="form-label">Form Type <span class="text-danger">*</span></label>
                            <select class="form-select @error('form_type') is-invalid @enderror" 
                                    id="form_type" 
                                    name="form_type"
                                    required>
                                <option value="">Select form type...</option>
                                @foreach($formTypes as $value => $label)
                                    <option value="{{ $value }}" {{ old('form_type') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                The type of customs form this template represents.
                            </div>
                            @error('form_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" 
                                      name="description"
                                      rows="3"
                                      placeholder="Optional description of when and how this form is used...">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="document" class="form-label">Template File <span class="text-danger">*</span></label>
                            <input type="file" 
                                   class="form-control @error('document') is-invalid @enderror" 
                                   id="document" 
                                   name="document"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.txt"
                                   required>
                            <div class="form-text">
                                Supported formats: PDF, Word (.doc, .docx), Excel (.xlsx, .xls), Text (.txt). Max size: 50MB
                            </div>
                            @error('document')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>About Form Templates:</strong>
                            <p class="mb-0 mt-2">
                                Form templates are blank or sample customs forms that BoVi Customs uses as a reference when 
                                generating declaration documents for users. Upload the official forms used by the country's 
                                customs authority.
                            </p>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.country-documents.index', ['tab' => 'templates']) }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-1"></i> Upload Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
