@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-upload me-2 text-primary"></i>FTP Submission Testing
            </h1>
            <p class="text-muted mb-0">Test FTP connections and file uploads for countries with FTP enabled</p>
        </div>
        <a href="{{ route('admin.countries.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Countries
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <!-- Connection Test Panel -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plug me-2"></i>Test FTP Connection</h5>
                </div>
                <div class="card-body">
                    <form id="connectionTestForm">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Country <span class="text-danger">*</span></label>
                            <select name="country_id" id="countrySelect" class="form-select" required>
                                <option value="">Select a country...</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}" 
                                            data-host="{{ $country->ftp_host }}"
                                            data-port="{{ $country->ftp_port }}">
                                        {{ $country->name }} ({{ $country->ftp_host }}:{{ $country->ftp_port }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div id="ftpDetails" class="mb-3 d-none">
                            <div class="alert alert-info mb-3">
                                <strong>FTP Server:</strong> <code id="ftpHostDisplay"></code>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">FTP Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required placeholder="Enter FTP username">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">FTP Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required placeholder="Enter FTP password">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Trader ID <span class="text-danger">*</span></label>
                            <input type="text" name="trader_id" class="form-control" required maxlength="10" placeholder="e.g., 123456">
                            <div class="form-text">6-digit trader ID used for file naming</div>
                        </div>

                        <button type="submit" class="btn btn-primary" id="testConnectionBtn">
                            <i class="fas fa-plug me-1"></i>Test Connection
                        </button>
                    </form>

                    <div id="connectionResult" class="mt-3 d-none">
                        <div class="alert" id="connectionResultAlert">
                            <span id="connectionResultMessage"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- T12 Preview & Submit Panel -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-file-code me-2"></i>Generate & Submit T12</h5>
                </div>
                <div class="card-body">
                    <form id="submitForm">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Declaration <span class="text-danger">*</span></label>
                            <select name="declaration_id" id="declarationSelect" class="form-select" required>
                                <option value="">Select a declaration...</option>
                                @foreach($declarations as $declaration)
                                    @php
                                        $consignee = $declaration->consigneeContact ?? $declaration->shipment?->consigneeContact;
                                        $consigneeTraderId = $consignee?->customs_registration_id ?? '';
                                    @endphp
                                    <option value="{{ $declaration->id }}" 
                                            data-country="{{ $declaration->country_id }}"
                                            data-trader-id="{{ $consigneeTraderId }}"
                                            data-consignee="{{ $consignee?->company_name ?? '' }}">
                                        {{ $declaration->form_number ?? ('#' . $declaration->id) }} 
                                        - {{ $declaration->country?->name ?? 'Unknown' }}
                                        - {{ $declaration->organization?->name ?? 'No Org' }}
                                        @if($consigneeTraderId)
                                            [ID: {{ $consigneeTraderId }}]
                                        @endif
                                        ({{ $declaration->created_at->format('M d, Y') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Trader ID for T12 <span class="text-danger">*</span></label>
                            <input type="text" name="submit_trader_id" id="submitTraderId" class="form-control" required maxlength="10" placeholder="e.g., 123456">
                            <div id="traderIdSource" class="form-text d-none">
                                <i class="fas fa-check-circle text-success me-1"></i>
                                Auto-filled from consignee: <strong id="consigneeName"></strong>
                            </div>
                        </div>

                        <div class="btn-group w-100 mb-3">
                            <button type="button" class="btn btn-outline-primary" id="previewT12Btn">
                                <i class="fas fa-eye me-1"></i>Preview T12
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="downloadT12Btn">
                                <i class="fas fa-download me-1"></i>Download T12
                            </button>
                        </div>

                        <hr>

                        <div class="mb-3">
                            <label class="form-label">FTP Credentials for Submission</label>
                            <div class="input-group mb-2">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" name="submit_username" id="submitUsername" class="form-control" placeholder="FTP Username">
                            </div>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" name="submit_password" id="submitPassword" class="form-control" placeholder="FTP Password">
                            </div>
                            <div class="form-text">Use credentials from the connection test or enter different ones</div>
                        </div>

                        <button type="button" class="btn btn-success w-100" id="submitFtpBtn">
                            <i class="fas fa-upload me-1"></i>Submit via FTP
                        </button>
                    </form>

                    <div id="submitResult" class="mt-3 d-none">
                        <div class="alert" id="submitResultAlert">
                            <span id="submitResultMessage"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- T12 Preview Modal -->
    <div class="modal fade" id="t12PreviewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-code me-2"></i>T12 File Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="t12PreviewContent">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="modalDownloadBtn">
                        <i class="fas fa-download me-1"></i>Download
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Organization Credentials Reference -->
    @if($orgCredentials->count() > 0)
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-key me-2"></i>Organization FTP Credentials</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">These are FTP credentials configured by organizations. You can reference their Trader IDs for testing.</p>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Organization</th>
                            <th>Country</th>
                            <th>Trader ID</th>
                            <th>Display Name</th>
                            <th>Status</th>
                            <th>Last Tested</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orgCredentials as $cred)
                        <tr>
                            <td>{{ $cred->organization->name ?? 'Unknown' }}</td>
                            <td>{{ $cred->country->name ?? 'Unknown' }}</td>
                            <td><code>{{ $cred->trader_id }}</code></td>
                            <td>{{ $cred->display_name ?? '-' }}</td>
                            <td>
                                @if($cred->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>{{ $cred->last_tested_at?->format('M d, Y H:i') ?? 'Never' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Countries with FTP Enabled -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Countries with FTP Enabled</h5>
        </div>
        <div class="card-body">
            @if($countries->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Country</th>
                                <th>FTP Host</th>
                                <th>Port</th>
                                <th>Base Path</th>
                                <th>File Format</th>
                                <th>Passive Mode</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($countries as $country)
                            <tr>
                                <td>
                                    <strong>{{ $country->name }}</strong>
                                    <br><small class="text-muted">{{ $country->code }}</small>
                                </td>
                                <td><code>{{ $country->ftp_host }}</code></td>
                                <td>{{ $country->ftp_port ?? 21 }}</td>
                                <td><code>{{ $country->ftp_base_path ?? '/' }}</code></td>
                                <td>
                                    <span class="badge bg-primary">
                                        {{ strtoupper(str_replace('_', ' ', $country->ftp_file_format ?? 'caps_t12')) }}
                                    </span>
                                </td>
                                <td>
                                    @if($country->ftp_passive_mode)
                                        <span class="badge bg-info">Yes</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.countries.edit', $country) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-cog"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-4">
                    <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No countries have FTP submission enabled yet.</p>
                    <a href="{{ route('admin.countries.index') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Configure a Country
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const countrySelect = document.getElementById('countrySelect');
    const ftpDetails = document.getElementById('ftpDetails');
    const ftpHostDisplay = document.getElementById('ftpHostDisplay');
    
    // Show FTP details when country is selected
    countrySelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        if (selected.value) {
            ftpHostDisplay.textContent = selected.dataset.host + ':' + selected.dataset.port;
            ftpDetails.classList.remove('d-none');
        } else {
            ftpDetails.classList.add('d-none');
        }
    });

    // Auto-populate Trader ID when declaration is selected
    document.getElementById('declarationSelect').addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        const traderId = selected.dataset.traderId;
        const consigneeName = selected.dataset.consignee;
        const traderIdInput = document.getElementById('submitTraderId');
        const traderIdSource = document.getElementById('traderIdSource');
        const consigneeNameSpan = document.getElementById('consigneeName');
        
        if (traderId) {
            traderIdInput.value = traderId;
            consigneeNameSpan.textContent = consigneeName;
            traderIdSource.classList.remove('d-none');
        } else {
            traderIdSource.classList.add('d-none');
            // Don't clear if user has already entered something
            if (!traderIdInput.value) {
                traderIdInput.placeholder = 'Enter Trader ID (not found in consignee)';
            }
        }
    });

    // Test Connection
    document.getElementById('connectionTestForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('testConnectionBtn');
        const resultDiv = document.getElementById('connectionResult');
        const resultAlert = document.getElementById('connectionResultAlert');
        const resultMessage = document.getElementById('connectionResultMessage');
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Testing...';
        
        const formData = new FormData(this);
        
        fetch('{{ route("admin.ftp-test.test-connection") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            resultDiv.classList.remove('d-none');
            resultAlert.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
            resultMessage.innerHTML = '<i class="fas ' + (data.success ? 'fa-check-circle' : 'fa-times-circle') + ' me-1"></i>' + data.message;
            
            // Copy credentials to submit form if successful
            if (data.success) {
                document.getElementById('submitUsername').value = formData.get('username');
                document.getElementById('submitPassword').value = formData.get('password');
                document.getElementById('submitTraderId').value = formData.get('trader_id');
            }
        })
        .catch(error => {
            resultDiv.classList.remove('d-none');
            resultAlert.className = 'alert alert-danger';
            resultMessage.innerHTML = '<i class="fas fa-times-circle me-1"></i>Error: ' + error.message;
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plug me-1"></i>Test Connection';
        });
    });

    // Preview T12
    document.getElementById('previewT12Btn').addEventListener('click', function() {
        const declarationId = document.getElementById('declarationSelect').value;
        const traderId = document.getElementById('submitTraderId').value;
        
        if (!declarationId) {
            alert('Please select a declaration');
            return;
        }
        if (!traderId) {
            alert('Please enter a Trader ID');
            return;
        }
        
        const modal = new bootstrap.Modal(document.getElementById('t12PreviewModal'));
        const contentDiv = document.getElementById('t12PreviewContent');
        
        contentDiv.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
        modal.show();
        
        fetch('{{ route("admin.ftp-test.preview-t12") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                declaration_id: declarationId,
                trader_id: traderId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.preview) {
                let html = '<div class="mb-3">';
                html += '<strong>Filename:</strong> <code>' + (data.preview.filename || 'N/A') + '</code><br>';
                html += '<strong>Lines:</strong> ' + (data.preview.line_count || 0) + '<br>';
                html += '<strong>Size:</strong> ' + (data.preview.size || 0) + ' bytes';
                html += '</div>';
                
                if (data.preview.validation_errors && data.preview.validation_errors.length > 0) {
                    html += '<div class="alert alert-warning"><strong>Validation Issues:</strong><ul class="mb-0">';
                    data.preview.validation_errors.forEach(err => {
                        html += '<li>' + escapeHtml(err) + '</li>';
                    });
                    html += '</ul></div>';
                }
                
                if (data.preview.record_counts && typeof data.preview.record_counts === 'object') {
                    html += '<h6>Record Summary</h6>';
                    html += '<table class="table table-sm"><tbody>';
                    for (const [key, value] of Object.entries(data.preview.record_counts)) {
                        html += '<tr><td>' + escapeHtml(key) + '</td><td>' + value + '</td></tr>';
                    }
                    html += '</tbody></table>';
                }
                
                if (data.preview.content || data.preview.raw_content) {
                    html += '<h6>Raw Content</h6>';
                    html += '<pre class="bg-dark text-light p-3 rounded" style="max-height: 400px; overflow: auto; font-size: 12px;">' + 
                            escapeHtml(data.preview.content || data.preview.raw_content) + '</pre>';
                }
                
                contentDiv.innerHTML = html;
            } else {
                contentDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + 
                    escapeHtml(data.message || 'Unknown error occurred') + '</div>';
            }
        })
        .catch(error => {
            contentDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
        });
    });

    // Download T12
    document.getElementById('downloadT12Btn').addEventListener('click', function() {
        const declarationId = document.getElementById('declarationSelect').value;
        const traderId = document.getElementById('submitTraderId').value;
        
        if (!declarationId) {
            alert('Please select a declaration');
            return;
        }
        
        window.location.href = '{{ url("admin/ftp-test/download") }}/' + declarationId + '?trader_id=' + (traderId || '000000');
    });

    // Modal download button
    document.getElementById('modalDownloadBtn').addEventListener('click', function() {
        document.getElementById('downloadT12Btn').click();
    });

    // Submit via FTP
    document.getElementById('submitFtpBtn').addEventListener('click', function() {
        const declarationId = document.getElementById('declarationSelect').value;
        const traderId = document.getElementById('submitTraderId').value;
        const username = document.getElementById('submitUsername').value;
        const password = document.getElementById('submitPassword').value;
        const declaration = document.getElementById('declarationSelect').options[document.getElementById('declarationSelect').selectedIndex];
        const countryId = declaration?.dataset?.country;
        
        if (!declarationId || !traderId || !username || !password) {
            alert('Please fill in all fields (Declaration, Trader ID, Username, Password)');
            return;
        }
        
        if (!confirm('Are you sure you want to submit this declaration via FTP?')) {
            return;
        }
        
        const btn = this;
        const resultDiv = document.getElementById('submitResult');
        const resultAlert = document.getElementById('submitResultAlert');
        const resultMessage = document.getElementById('submitResultMessage');
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Submitting...';
        
        fetch('{{ route("admin.ftp-test.submit") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                declaration_id: declarationId,
                country_id: countryId,
                trader_id: traderId,
                username: username,
                password: password
            })
        })
        .then(response => response.json())
        .then(data => {
            resultDiv.classList.remove('d-none');
            resultAlert.className = 'alert ' + (data.success ? 'alert-success' : 'alert-danger');
            
            let msg = '<i class="fas ' + (data.success ? 'fa-check-circle' : 'fa-times-circle') + ' me-1"></i>' + data.message;
            if (data.filename) {
                msg += '<br><small>Filename: <code>' + data.filename + '</code></small>';
            }
            resultMessage.innerHTML = msg;
        })
        .catch(error => {
            resultDiv.classList.remove('d-none');
            resultAlert.className = 'alert alert-danger';
            resultMessage.innerHTML = '<i class="fas fa-times-circle me-1"></i>Error: ' + error.message;
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload me-1"></i>Submit via FTP';
        });
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@endpush
@endsection
