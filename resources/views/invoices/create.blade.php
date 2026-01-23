@extends('layouts.app')

@section('title', 'Upload Invoice - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-upload me-2"></i>Upload Invoice</h2>
            <p class="text-muted mb-0">Upload your commercial invoice for AI-powered customs classification</p>
        </div>
        <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>View Invoices
        </a>
    </div>

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Usage Indicator --}}
    @if(isset($used) && isset($limit) && $limit)
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        Monthly usage: <strong>{{ $used }}</strong> of <strong>{{ $limit }}</strong> invoices
        @if($limit - $used <= 3)
            <span class="badge bg-warning text-dark ms-2">Running low</span>
        @endif
    </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body p-4">
                    <form action="{{ route('invoices.store') }}" method="POST" enctype="multipart/form-data" id="invoice-upload-form">
                        @csrf
                        
                        {{-- Country Selection --}}
                        <div class="mb-4">
                            <label for="country_id" class="form-label fw-semibold">
                                <i class="fas fa-flag me-1"></i>Select Country <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-lg @error('country_id') is-invalid @enderror" 
                                    id="country_id" name="country_id" required>
                                <option value="">-- Select the country for customs classification --</option>
                                @foreach($countries as $country)
                                <option value="{{ $country->id }}" {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                    {{ $country->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('country_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">The customs codes and duty rates will be based on this country's tariff schedule.</small>
                        </div>

                        {{-- File Upload Area --}}
                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-file-invoice me-1"></i>Invoice File <span class="text-danger">*</span>
                            </label>
                            <div id="drop-area" class="border border-2 border-dashed rounded p-5 text-center bg-light @error('invoice_file') border-danger @enderror">
                                <i class="fas fa-cloud-upload-alt fa-4x mb-3 text-primary" id="upload-icon"></i>
                                <p class="mb-2 fs-5" id="drop-text">Drag and drop your invoice file here</p>
                                <p class="text-muted" id="drop-or">or</p>
                                <button type="button" class="btn btn-primary btn-lg mt-2" id="selectFileBtn">
                                    <i class="fas fa-folder-open me-2"></i>Select File
                                </button>
                                <input type="file" class="d-none" id="invoice_file" name="invoice_file" 
                                       accept=".pdf,.jpg,.jpeg,.png,.tiff,.xls,.xlsx" required>
                            </div>
                            @error('invoice_file')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                            
                            {{-- File Info (shown after selection) --}}
                            <div id="file-info" class="mt-3 d-none">
                                <div class="alert alert-success d-flex align-items-center mb-0">
                                    <i class="fas fa-check-circle fa-lg me-3"></i>
                                    <div>
                                        <strong>File Selected:</strong> <span id="file-name"></span>
                                        <span id="file-size" class="ms-2 text-muted"></span>
                                        <button type="button" class="btn btn-sm btn-link text-danger ms-2 p-0" id="clearFileBtn">
                                            <i class="fas fa-times"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Progress Bar --}}
                        <div class="progress mb-4 d-none" style="height: 25px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%">0%</div>
                        </div>

                        {{-- Submit Button --}}
                        <button type="submit" class="btn btn-success btn-lg w-100" id="submitBtn">
                            <i class="fas fa-robot me-2"></i>Upload and Extract with AI
                        </button>
                    </form>
                </div>
            </div>

            {{-- Supported Formats Info --}}
            <div class="card mt-4 border-0 bg-light">
                <div class="card-body">
                    <h6 class="fw-semibold"><i class="fas fa-info-circle me-2 text-info"></i>Supported Formats</h6>
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file-pdf fa-2x text-danger me-3"></i>
                                <div>
                                    <strong>PDF</strong>
                                    <small class="d-block text-muted">Text & scanned</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file-image fa-2x text-success me-3"></i>
                                <div>
                                    <strong>Images</strong>
                                    <small class="d-block text-muted">JPG, PNG, TIFF</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-file-excel fa-2x text-success me-3"></i>
                                <div>
                                    <strong>Excel</strong>
                                    <small class="d-block text-muted">XLS, XLSX</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <small class="text-muted">
                        <i class="fas fa-weight me-1"></i>Maximum file size: 10MB
                    </small>
                </div>
            </div>

            {{-- How It Works --}}
            <div class="card mt-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-magic me-2 text-primary"></i>How It Works</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4 text-center">
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">1</div>
                            <h6>Upload</h6>
                            <small class="text-muted">Upload your commercial invoice</small>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">2</div>
                            <h6>AI Extraction</h6>
                            <small class="text-muted">Our AI extracts line items</small>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 40px; height: 40px;">3</div>
                            <h6>Classification</h6>
                            <small class="text-muted">Get HS codes & duty rates</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Processing Modal -->
<div class="modal fade" id="processingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Processing...</span>
                </div>
                <h5>Processing Invoice</h5>
                <p class="text-muted mb-0">Our AI is extracting data from your invoice. This may take a moment...</p>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-circle me-2"></i>Upload Error</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="error-message"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
#drop-area {
    transition: all 0.3s ease;
    cursor: pointer;
}
#drop-area:hover,
#drop-area.highlight {
    background-color: #e3f2fd !important;
    border-color: #2196f3 !important;
}
#drop-area.drag-over {
    background-color: #e8f5e9 !important;
    border-color: #4caf50 !important;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropArea = document.getElementById('drop-area');
    const fileInput = document.getElementById('invoice_file');
    const fileInfo = document.getElementById('file-info');
    const fileName = document.getElementById('file-name');
    const fileSize = document.getElementById('file-size');
    const selectFileBtn = document.getElementById('selectFileBtn');
    const clearFileBtn = document.getElementById('clearFileBtn');
    const uploadIcon = document.getElementById('upload-icon');
    const dropText = document.getElementById('drop-text');
    const dropOr = document.getElementById('drop-or');
    const form = document.getElementById('invoice-upload-form');
    const progressBar = document.querySelector('.progress-bar');
    const progressContainer = document.querySelector('.progress');
    const submitBtn = document.getElementById('submitBtn');
    const processingModal = new bootstrap.Modal(document.getElementById('processingModal'));
    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
    const errorMessage = document.getElementById('error-message');

    // Prevent defaults for drag events
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Highlight drop area
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, () => dropArea.classList.add('drag-over'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, () => dropArea.classList.remove('drag-over'), false);
    });

    // Handle drop
    dropArea.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            fileInput.files = files;
            updateFileInfo();
        }
    });

    // Click on "Select File" button
    selectFileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        fileInput.click();
    });

    // Click anywhere in drop area (except button)
    dropArea.addEventListener('click', function(e) {
        if (e.target !== selectFileBtn && !selectFileBtn.contains(e.target)) {
            fileInput.click();
        }
    });

    // File input change
    fileInput.addEventListener('change', function() {
        console.log('File input changed, files:', fileInput.files.length);
        updateFileInfo();
    });

    // Clear file button
    clearFileBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fileInput.value = '';
        updateFileInfo();
    });

    function updateFileInfo() {
        console.log('updateFileInfo called, files:', fileInput.files.length);
        if (fileInput.files && fileInput.files.length > 0) {
            const file = fileInput.files[0];
            fileName.textContent = file.name;
            fileSize.textContent = '(' + formatFileSize(file.size) + ')';
            fileInfo.classList.remove('d-none');
            
            // Update drop area to show file is selected
            dropArea.classList.add('border-success');
            dropArea.classList.remove('border-dashed');
            uploadIcon.classList.remove('text-primary');
            uploadIcon.classList.add('text-success');
            uploadIcon.classList.remove('fa-cloud-upload-alt');
            uploadIcon.classList.add('fa-check-circle');
            dropText.textContent = 'File ready to upload';
            dropOr.classList.add('d-none');
            selectFileBtn.innerHTML = '<i class="fas fa-sync-alt me-2"></i>Change File';
        } else {
            fileInfo.classList.add('d-none');
            
            // Reset drop area
            dropArea.classList.remove('border-success');
            dropArea.classList.add('border-dashed');
            uploadIcon.classList.add('text-primary');
            uploadIcon.classList.remove('text-success');
            uploadIcon.classList.add('fa-cloud-upload-alt');
            uploadIcon.classList.remove('fa-check-circle');
            dropText.textContent = 'Drag and drop your invoice file here';
            dropOr.classList.remove('d-none');
            selectFileBtn.innerHTML = '<i class="fas fa-folder-open me-2"></i>Select File';
        }
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // Validate
        const countrySelect = document.getElementById('country_id');
        if (!countrySelect.value) {
            countrySelect.classList.add('is-invalid');
            countrySelect.focus();
            return;
        }

        if (!fileInput.files.length) {
            errorMessage.textContent = 'Please select a file to upload.';
            errorModal.show();
            return;
        }

        // Show processing modal
        processingModal.show();
        submitBtn.disabled = true;

        const formData = new FormData(form);
        const xhr = new XMLHttpRequest();

        xhr.open('POST', form.action);

        // Progress tracking
        xhr.upload.addEventListener('progress', function(event) {
            if (event.lengthComputable) {
                const percentComplete = Math.round((event.loaded / event.total) * 100);
                progressBar.style.width = percentComplete + '%';
                progressBar.textContent = percentComplete + '%';
                progressContainer.classList.remove('d-none');
            }
        });

        xhr.onload = function() {
            processingModal.hide();
            if (xhr.status === 200 || xhr.status === 302) {
                // Follow redirect
                window.location.href = xhr.responseURL;
            } else {
                submitBtn.disabled = false;
                progressContainer.classList.add('d-none');
                
                // Try to parse error message
                let msg = 'Upload failed. Please try again.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) msg = response.message;
                } catch (e) {
                    // Check if it's a redirect in the response
                    if (xhr.responseText.includes('error')) {
                        msg = 'There was an issue processing your invoice. Please try again.';
                    }
                }
                errorMessage.textContent = msg;
                errorModal.show();
            }
        };

        xhr.onerror = function() {
            processingModal.hide();
            submitBtn.disabled = false;
            progressContainer.classList.add('d-none');
            errorMessage.textContent = 'A network error occurred. Please check your connection and try again.';
            errorModal.show();
        };

        xhr.send(formData);
    });

    // Remove invalid state on change
    document.getElementById('country_id').addEventListener('change', function() {
        this.classList.remove('is-invalid');
    });
});
</script>
@endpush
