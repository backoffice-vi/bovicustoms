@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">Upload Invoice</h2>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('invoices.store') }}" method="POST" enctype="multipart/form-data" id="invoice-upload-form">
                        @csrf
                        <div class="mb-4">
                            <div id="drop-area" class="border border-dashed border-secondary p-5 text-center">
                                <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
                                <p class="mb-2">Drag and drop your invoice file here</p>
                                <p>or</p>
                                <label for="invoice_file" class="btn btn-primary mt-2">Select File</label>
                                <input type="file" class="d-none" id="invoice_file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png,.tiff">
                            </div>
                        </div>
                        <div id="file-info" class="mb-3 d-none">
                            <p>Selected file: <span id="file-name"></span></p>
                        </div>
                        <div class="progress mb-3 d-none">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <button type="submit" class="btn btn-success btn-lg w-100">Upload and Process</button>
                    </form>
                </div>
            </div>
            <div class="mt-3">
                <h5>Supported Formats:</h5>
                <p>PDF, TIFF, JPG, PNG (Max file size: 10MB)</p>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="errorModalLabel">Upload Error</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

@push('scripts')
<script>
    const dropArea = document.getElementById('drop-area');
    const fileInput = document.getElementById('invoice_file');
    const fileInfo = document.getElementById('file-info');
    const fileName = document.getElementById('file-name');
    const form = document.getElementById('invoice-upload-form');
    const progressBar = document.querySelector('.progress-bar');
    const progressContainer = document.querySelector('.progress');
    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
    const errorMessage = document.getElementById('error-message');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
        dropArea.classList.add('bg-light');
    }

    function unhighlight() {
        dropArea.classList.remove('bg-light');
    }

    dropArea.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        updateFileInfo();
    }

    fileInput.addEventListener('change', updateFileInfo);

    function updateFileInfo() {
        if (fileInput.files.length > 0) {
            fileName.textContent = fileInput.files[0].name;
            fileInfo.classList.remove('d-none');
        } else {
            fileInfo.classList.add('d-none');
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        const xhr = new XMLHttpRequest();

        xhr.open('POST', form.action);
        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                const percentComplete = (event.loaded / event.total) * 100;
                progressBar.style.width = percentComplete + '%';
                progressBar.textContent = percentComplete.toFixed(2) + '%';
                progressContainer.classList.remove('d-none');
            }
        });

        xhr.onload = function() {
            if (xhr.status === 200) {
                window.location.href = xhr.responseURL;
            } else {
                errorMessage.textContent = 'Upload failed. Please try again.';
                errorModal.show();
            }
        };

        xhr.onerror = function() {
            errorMessage.textContent = 'An error occurred during the upload. Please try again.';
            errorModal.show();
        };

        xhr.send(formData);
    });
</script>
@endpush
