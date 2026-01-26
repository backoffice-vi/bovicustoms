@extends('layouts.app')

@section('title', 'Submit Declaration - Agent Portal')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-paper-plane me-2"></i>Submit Declaration</h2>
            <p class="text-muted mb-0">{{ $declaration->form_number }} - {{ $declaration->organization->name ?? 'N/A' }}</p>
        </div>
        <a href="{{ route('agent.declarations.show', $declaration) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <!-- File Upload Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-upload me-2"></i>Upload Documents</strong>
                </div>
                <div class="card-body">
                    <div id="upload-area" class="border border-dashed rounded p-4 text-center mb-3" 
                         style="border-style: dashed !important; cursor: pointer;">
                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                        <h5>Drag & Drop Files Here</h5>
                        <p class="text-muted mb-2">or click to browse</p>
                        <small class="text-muted">
                            Allowed: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX (Max 10MB each)
                        </small>
                        <input type="file" id="file-input" class="d-none" multiple 
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Document Type</label>
                            <select id="document-type" class="form-select">
                                @foreach($documentTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description (Optional)</label>
                            <input type="text" id="file-description" class="form-control" 
                                   placeholder="Brief description of the document">
                        </div>
                    </div>

                    <!-- Upload Progress -->
                    <div id="upload-progress" class="d-none mb-3">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%"></div>
                        </div>
                        <small class="text-muted">Uploading...</small>
                    </div>
                </div>
            </div>

            <!-- Uploaded Files -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-paperclip me-2"></i>Uploaded Documents</strong>
                    <span class="badge bg-primary" id="attachment-count">{{ $declaration->submissionAttachments->count() }}</span>
                </div>
                <div class="card-body">
                    <div id="attachments-list">
                        @if($declaration->submissionAttachments->count() > 0)
                            @foreach($declaration->submissionAttachments as $attachment)
                                <div class="d-flex justify-content-between align-items-center border-bottom py-2" 
                                     id="attachment-{{ $attachment->id }}">
                                    <div>
                                        <i class="{{ $attachment->file_icon }} me-2"></i>
                                        <strong>{{ $attachment->file_name }}</strong>
                                        <br>
                                        <small class="text-muted">
                                            {{ $attachment->document_type_label }} | {{ $attachment->formatted_file_size }}
                                            @if($attachment->description)
                                                | {{ $attachment->description }}
                                            @endif
                                        </small>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('agent.attachments.download', $attachment) }}" 
                                           class="btn btn-outline-primary">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger delete-attachment" 
                                                data-id="{{ $attachment->id }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <p class="text-muted text-center mb-0" id="no-attachments">
                                No documents uploaded yet. Upload files above.
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Submission Form -->
            <div class="card">
                <div class="card-header">
                    <strong><i class="fas fa-check-circle me-2"></i>Confirm Submission</strong>
                </div>
                <div class="card-body">
                    <form action="{{ route('agent.declarations.submit', $declaration) }}" method="POST">
                        @csrf
                        
                        <div class="mb-3">
                            <label class="form-label">Reference Number (Optional)</label>
                            <input type="text" name="submission_reference" class="form-control" 
                                   placeholder="External reference number if any"
                                   value="{{ old('submission_reference') }}">
                            <small class="text-muted">Enter a reference number from the customs system if available</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea name="submission_notes" class="form-control" rows="3" 
                                      placeholder="Any notes about this submission...">{{ old('submission_notes') }}</textarea>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> Once submitted, this declaration cannot be modified. 
                            Please ensure all information is correct and all required documents are uploaded.
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('agent.declarations.show', $declaration) }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Submit Declaration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Declaration Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <strong><i class="fas fa-info-circle me-2"></i>Declaration Summary</strong>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Form Number</dt>
                        <dd><code>{{ $declaration->form_number }}</code></dd>
                        
                        <dt>Client</dt>
                        <dd>{{ $declaration->organization->name ?? 'N/A' }}</dd>
                        
                        <dt>Country</dt>
                        <dd>{{ $declaration->country->name ?? 'N/A' }}</dd>
                        
                        <dt>CIF Value</dt>
                        <dd class="fs-5">${{ number_format($declaration->cif_value ?? 0, 2) }}</dd>
                        
                        <dt>Total Duty Payable</dt>
                        <dd class="fs-4 text-success fw-bold">${{ number_format($declaration->total_duty ?? 0, 2) }}</dd>
                        
                        <dt>Line Items</dt>
                        <dd>{{ $declaration->declarationItems->count() }} items</dd>
                    </dl>
                </div>
            </div>

            <!-- Submission Checklist -->
            <div class="card">
                <div class="card-header">
                    <strong><i class="fas fa-tasks me-2"></i>Checklist</strong>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Declaration form complete
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Duty calculated
                        </li>
                        <li class="mb-2" id="checklist-attachments">
                            @if($declaration->submissionAttachments->count() > 0)
                                <i class="fas fa-check-circle text-success me-2"></i>
                            @else
                                <i class="fas fa-exclamation-circle text-warning me-2"></i>
                            @endif
                            Documents uploaded
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-circle text-muted me-2"></i>
                            Ready to submit
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('file-input');
    const documentType = document.getElementById('document-type');
    const fileDescription = document.getElementById('file-description');
    const uploadProgress = document.getElementById('upload-progress');
    const attachmentsList = document.getElementById('attachments-list');
    const attachmentCount = document.getElementById('attachment-count');
    const noAttachments = document.getElementById('no-attachments');

    // Click to upload
    uploadArea.addEventListener('click', () => fileInput.click());

    // Drag and drop
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('bg-light');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('bg-light');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('bg-light');
        handleFiles(e.dataTransfer.files);
    });

    // File input change
    fileInput.addEventListener('change', () => {
        handleFiles(fileInput.files);
        fileInput.value = ''; // Reset for next upload
    });

    function handleFiles(files) {
        Array.from(files).forEach(file => uploadFile(file));
    }

    function uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('document_type', documentType.value);
        formData.append('description', fileDescription.value);

        uploadProgress.classList.remove('d-none');
        const progressBar = uploadProgress.querySelector('.progress-bar');
        progressBar.style.width = '0%';

        fetch('{{ route("agent.declarations.upload-attachment", $declaration) }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            uploadProgress.classList.add('d-none');
            
            if (data.success) {
                addAttachmentToList(data.attachment);
                fileDescription.value = '';
                updateAttachmentCount(1);
            } else {
                alert('Upload failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            uploadProgress.classList.add('d-none');
            alert('Upload failed: ' + error.message);
        });

        // Simulate progress
        let progress = 0;
        const interval = setInterval(() => {
            progress += 10;
            progressBar.style.width = Math.min(progress, 90) + '%';
            if (progress >= 90) clearInterval(interval);
        }, 100);
    }

    function addAttachmentToList(attachment) {
        if (noAttachments) {
            noAttachments.remove();
        }

        const html = `
            <div class="d-flex justify-content-between align-items-center border-bottom py-2" 
                 id="attachment-${attachment.id}">
                <div>
                    <i class="${attachment.file_icon} me-2"></i>
                    <strong>${attachment.file_name}</strong>
                    <br>
                    <small class="text-muted">
                        ${attachment.document_type_label} | ${attachment.formatted_file_size}
                    </small>
                </div>
                <div class="btn-group btn-group-sm">
                    <a href="/agent/attachments/${attachment.id}/download" class="btn btn-outline-primary">
                        <i class="fas fa-download"></i>
                    </a>
                    <button type="button" class="btn btn-outline-danger delete-attachment" 
                            data-id="${attachment.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        attachmentsList.insertAdjacentHTML('beforeend', html);
        
        // Update checklist
        const checklistItem = document.getElementById('checklist-attachments');
        checklistItem.innerHTML = '<i class="fas fa-check-circle text-success me-2"></i>Documents uploaded';
    }

    function updateAttachmentCount(delta) {
        const current = parseInt(attachmentCount.textContent);
        attachmentCount.textContent = current + delta;
    }

    // Delete attachment
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-attachment')) {
            const btn = e.target.closest('.delete-attachment');
            const attachmentId = btn.dataset.id;
            
            if (confirm('Are you sure you want to delete this attachment?')) {
                fetch(`/agent/attachments/${attachmentId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById(`attachment-${attachmentId}`).remove();
                        updateAttachmentCount(-1);
                        
                        // Check if no more attachments
                        if (parseInt(attachmentCount.textContent) === 0) {
                            attachmentsList.innerHTML = '<p class="text-muted text-center mb-0" id="no-attachments">No documents uploaded yet. Upload files above.</p>';
                            const checklistItem = document.getElementById('checklist-attachments');
                            checklistItem.innerHTML = '<i class="fas fa-exclamation-circle text-warning me-2"></i>Documents uploaded';
                        }
                    } else {
                        alert('Delete failed: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    alert('Delete failed: ' + error.message);
                });
            }
        }
    });
});
</script>
@endpush

@push('styles')
<style>
.border-dashed {
    border-style: dashed !important;
    border-width: 2px !important;
}
#upload-area:hover {
    background-color: #f8f9fa;
}
</style>
@endpush
@endsection
