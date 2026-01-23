@extends('layouts.app')

@section('title', 'Upload Support Document - Admin')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex align-items-center mb-4">
                <a href="{{ route('admin.country-documents.index', ['tab' => 'support']) }}" class="btn btn-outline-secondary me-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0">
                    <i class="fas fa-book me-2"></i>Upload Support Document
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
                    <form method="POST" action="{{ route('admin.country-documents.support.store') }}" enctype="multipart/form-data">
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
                                Select the country this support document is for.
                            </div>
                            @error('country_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="title" class="form-label">Document Title <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control @error('title') is-invalid @enderror" 
                                   id="title" 
                                   name="title"
                                   value="{{ old('title') }}"
                                   placeholder="e.g., BVI Customs Tariff Schedule 2026"
                                   required>
                            <div class="form-text">
                                A descriptive title for this document.
                            </div>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="document_type" class="form-label">Document Type <span class="text-danger">*</span></label>
                            <select class="form-select @error('document_type') is-invalid @enderror" 
                                    id="document_type" 
                                    name="document_type"
                                    required>
                                <option value="">Select document type...</option>
                                @foreach($documentTypes as $value => $label)
                                    <option value="{{ $value }}" {{ old('document_type') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                The type of support document this represents.
                            </div>
                            @error('document_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" 
                                      name="description"
                                      rows="3"
                                      placeholder="Optional description of the document's contents and purpose...">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="document" class="form-label">Document File <span class="text-danger">*</span></label>
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
                            <strong>About Support Documents:</strong>
                            <ol class="mb-0 mt-2">
                                <li>Upload government publications, tariff schedules, regulations, or guidelines</li>
                                <li>After uploading, click "Extract Text" to process the document</li>
                                <li>The AI will read this content when preparing customs forms for this country</li>
                                <li>Keep documents up-to-date by uploading new versions as regulations change</li>
                            </ol>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.country-documents.index', ['tab' => 'support']) }}" class="btn btn-outline-secondary">
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
