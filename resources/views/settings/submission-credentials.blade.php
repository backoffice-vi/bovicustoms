@extends('layouts.app')

@section('title', 'Submission Credentials')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-key text-primary me-2"></i>Submission Credentials
                    </h1>
                    <p class="text-muted mb-0">
                        Manage your credentials for submitting declarations via FTP or web portals
                    </p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCredentialModal">
                    <i class="fas fa-plus me-2"></i>Add Credentials
                </button>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <!-- Info Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-info-circle me-2"></i>About Submission Credentials
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-upload me-2 text-primary"></i>FTP Credentials</h6>
                            <p class="small text-muted mb-0">
                                For countries that support FTP submission (like BVI CAPS), you'll need your 
                                <strong>Trader ID</strong>, <strong>username</strong>, and <strong>password</strong> 
                                provided by the customs authority.
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-globe me-2 text-success"></i>Web Portal Credentials</h6>
                            <p class="small text-muted mb-0">
                                For web-based submission portals, enter your login credentials. These will be used 
                                to automatically log in and submit your declarations.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Credentials List -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Your Saved Credentials</h5>
                <span class="badge bg-primary">{{ $credentials->count() }} Credentials</span>
            </div>
        </div>
        <div class="card-body p-0">
            @if($credentials->isEmpty())
            <div class="text-center py-5">
                <i class="fas fa-key fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No credentials saved yet</h5>
                <p class="text-muted">Add your submission credentials to enable automated declaration submission</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCredentialModal">
                    <i class="fas fa-plus me-2"></i>Add Your First Credential
                </button>
            </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Status</th>
                            <th>Country</th>
                            <th>Type</th>
                            <th>Target/Details</th>
                            <th>Username</th>
                            <th>Last Used</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($credentials as $credential)
                        <tr class="{{ !$credential->is_active ? 'table-secondary' : '' }}">
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" 
                                           {{ $credential->is_active ? 'checked' : '' }}
                                           onchange="toggleCredential({{ $credential->id }}, this.checked)">
                                </div>
                            </td>
                            <td>
                                <span class="me-1">{{ $credential->country->flag_emoji ?? '' }}</span>
                                {{ $credential->country->name ?? 'Unknown' }}
                            </td>
                            <td>
                                @if($credential->isFtp())
                                    <span class="badge bg-primary"><i class="fas fa-upload me-1"></i>FTP</span>
                                @else
                                    <span class="badge bg-success"><i class="fas fa-globe me-1"></i>Web</span>
                                @endif
                            </td>
                            <td>
                                @if($credential->isFtp())
                                    <code>Trader ID: {{ $credential->trader_id ?? 'N/A' }}</code>
                                @else
                                    {{ $credential->webFormTarget->name ?? 'General' }}
                                @endif
                            </td>
                            <td>
                                <code>{{ $credential->decrypted_credentials['username'] ?? '***' }}</code>
                            </td>
                            <td>
                                @if($credential->last_used_at)
                                    <small class="text-muted">{{ $credential->last_used_at->diffForHumans() }}</small>
                                @else
                                    <small class="text-muted">Never</small>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-info" 
                                            onclick="testCredential({{ $credential->id }})"
                                            title="Test Connection">
                                        <i class="fas fa-plug"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" 
                                            onclick="editCredential({{ json_encode($credential) }})"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" 
                                            onclick="deleteCredential({{ $credential->id }})"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Add Credential Modal -->
<div class="modal fade" id="addCredentialModal" tabindex="-1" aria-labelledby="addCredentialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="credentialForm" method="POST" action="{{ route('settings.submission-credentials.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addCredentialModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Add Submission Credentials
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Country Selection -->
                        <div class="col-md-6">
                            <label for="country_id" class="form-label">Country <span class="text-danger">*</span></label>
                            <select class="form-select" id="country_id" name="country_id" required onchange="loadCountryOptions()">
                                <option value="">Select Country...</option>
                                @foreach($countries as $country)
                                <option value="{{ $country->id }}" 
                                        data-ftp="{{ $country->isFtpEnabled() ? 'true' : 'false' }}">
                                    {{ $country->flag_emoji }} {{ $country->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Credential Type -->
                        <div class="col-md-6">
                            <label for="credential_type" class="form-label">Credential Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="credential_type" name="credential_type" required onchange="toggleCredentialFields()">
                                <option value="">Select Type...</option>
                                <option value="ftp">FTP Submission</option>
                                <option value="web">Web Portal</option>
                            </select>
                        </div>

                        <!-- Web Target (for web type) -->
                        <div class="col-12 d-none" id="webTargetField">
                            <label for="web_form_target_id" class="form-label">Web Portal Target</label>
                            <select class="form-select" id="web_form_target_id" name="web_form_target_id">
                                <option value="">Select Target (or leave blank for general)...</option>
                            </select>
                        </div>

                        <!-- Display Name -->
                        <div class="col-12">
                            <label for="display_name" class="form-label">Display Name (Optional)</label>
                            <input type="text" class="form-control" id="display_name" name="display_name"
                                   placeholder="e.g., My CAPS Account">
                        </div>

                        <!-- FTP-specific: Trader ID -->
                        <div class="col-md-6 d-none" id="traderIdField">
                            <label for="trader_id" class="form-label">Trader ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="trader_id" name="trader_id"
                                   placeholder="e.g., 123456" maxlength="10">
                            <small class="text-muted">6-digit ID assigned by customs</small>
                        </div>

                        <!-- Username -->
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>

                        <!-- Password -->
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="passwordToggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- FTP-specific: Email -->
                        <div class="col-md-6 d-none" id="emailField">
                            <label for="email" class="form-label">Notification Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   placeholder="e.g., customs@yourcompany.com">
                            <small class="text-muted">Where CAPS will send notifications</small>
                        </div>

                        <!-- Notes -->
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"
                                      placeholder="Any additional notes about these credentials..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-info text-white" id="testConnectionBtn" onclick="testUnsavedCredential()">
                        <i class="fas fa-plug me-1"></i>Test Connection
                    </button>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i><span id="submitBtnText">Save Credentials</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Credentials</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete these credentials?</p>
                <p class="text-muted mb-0">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Test Result Modal -->
<div class="modal fade" id="testResultModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" id="testResultHeader">
                <h5 class="modal-title" id="testResultTitle">Connection Test</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="testResultContent"></div>
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
    const webTargets = @json($webTargets);
    const baseUrl = '{{ route("settings.submission-credentials.store") }}';
    const updateUrlBase = '{{ url("settings/submission-credentials") }}';

    function loadCountryOptions() {
        const countryId = document.getElementById('country_id').value;
        const credentialType = document.getElementById('credential_type');
        const ftpOption = credentialType.querySelector('option[value="ftp"]');
        
        // Check if country supports FTP
        const selectedOption = document.getElementById('country_id').selectedOptions[0];
        const ftpEnabled = selectedOption?.dataset.ftp === 'true';
        
        if (ftpOption) {
            ftpOption.disabled = !ftpEnabled;
            if (!ftpEnabled && credentialType.value === 'ftp') {
                credentialType.value = '';
            }
        }

        // Update web targets
        updateWebTargets(countryId);
        toggleCredentialFields();
    }

    function updateWebTargets(countryId) {
        const targetSelect = document.getElementById('web_form_target_id');
        targetSelect.innerHTML = '<option value="">Select Target (or leave blank for general)...</option>';
        
        if (countryId && webTargets[countryId]) {
            webTargets[countryId].forEach(target => {
                const option = document.createElement('option');
                option.value = target.id;
                option.textContent = target.name;
                targetSelect.appendChild(option);
            });
        }
    }

    function toggleCredentialFields() {
        const type = document.getElementById('credential_type').value;
        const traderIdField = document.getElementById('traderIdField');
        const emailField = document.getElementById('emailField');
        const webTargetField = document.getElementById('webTargetField');
        const traderIdInput = document.getElementById('trader_id');

        if (type === 'ftp') {
            traderIdField.classList.remove('d-none');
            emailField.classList.remove('d-none');
            webTargetField.classList.add('d-none');
            traderIdInput.required = true;
        } else if (type === 'web') {
            traderIdField.classList.add('d-none');
            emailField.classList.add('d-none');
            webTargetField.classList.remove('d-none');
            traderIdInput.required = false;
        } else {
            traderIdField.classList.add('d-none');
            emailField.classList.add('d-none');
            webTargetField.classList.add('d-none');
            traderIdInput.required = false;
        }
    }

    function togglePassword() {
        const input = document.getElementById('password');
        const icon = document.getElementById('passwordToggleIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }

    function resetForm() {
        document.getElementById('credentialForm').reset();
        document.getElementById('credentialForm').action = baseUrl;
        document.getElementById('formMethod').value = 'POST';
        document.getElementById('addCredentialModalLabel').innerHTML = '<i class="fas fa-plus-circle me-2"></i>Add Submission Credentials';
        document.getElementById('submitBtnText').textContent = 'Save Credentials';
        document.getElementById('password').required = true;
        toggleCredentialFields();
    }

    window.editCredential = function(credential) {
        resetForm();
        document.getElementById('credentialForm').action = updateUrlBase + '/' + credential.id;
        document.getElementById('formMethod').value = 'PUT';
        document.getElementById('addCredentialModalLabel').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Credentials';
        document.getElementById('submitBtnText').textContent = 'Update Credentials';
        
        document.getElementById('country_id').value = credential.country_id;
        document.getElementById('credential_type').value = credential.credential_type;
        document.getElementById('display_name').value = credential.display_name || '';
        document.getElementById('trader_id').value = credential.trader_id || '';
        document.getElementById('notes').value = credential.notes || '';
        
        // Password is optional when editing
        document.getElementById('password').required = false;
        document.getElementById('password').placeholder = 'Leave blank to keep current';
        
        loadCountryOptions();
        
        if (credential.web_form_target_id) {
            setTimeout(() => {
                document.getElementById('web_form_target_id').value = credential.web_form_target_id;
            }, 100);
        }
        
        if (credential.decrypted_credentials) {
            document.getElementById('username').value = credential.decrypted_credentials.username || '';
            document.getElementById('email').value = credential.decrypted_credentials.email || '';
        }
        
        new bootstrap.Modal(document.getElementById('addCredentialModal')).show();
    }

    window.deleteCredential = function(id) {
        document.getElementById('deleteForm').action = updateUrlBase + '/' + id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    window.toggleCredential = function(id, isActive) {
        fetch(updateUrlBase + '/' + id, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ is_active: isActive }),
        })
        .then(response => {
            if (!response.ok) {
                location.reload();
            }
        });
    }

    window.testCredential = function(id) {
        const modal = new bootstrap.Modal(document.getElementById('testResultModal'));
        document.getElementById('testResultContent').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Testing connection...</p></div>';
        document.getElementById('testResultHeader').className = 'modal-header';
        modal.show();

        fetch(updateUrlBase + '/' + id + '/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
        })
        .then(response => response.json())
        .then(data => {
            const header = document.getElementById('testResultHeader');
            const content = document.getElementById('testResultContent');
            
            if (data.success) {
                header.className = 'modal-header bg-success text-white';
                content.innerHTML = '<div class="text-center"><i class="fas fa-check-circle fa-3x text-success mb-3"></i><h5>Connection Successful!</h5><p>' + data.message + '</p></div>';
            } else {
                header.className = 'modal-header bg-danger text-white';
                content.innerHTML = '<div class="text-center"><i class="fas fa-times-circle fa-3x text-danger mb-3"></i><h5>Connection Failed</h5><p>' + data.message + '</p></div>';
            }
        })
        .catch(error => {
            document.getElementById('testResultHeader').className = 'modal-header bg-danger text-white';
            document.getElementById('testResultContent').innerHTML = '<div class="text-center"><i class="fas fa-times-circle fa-3x text-danger mb-3"></i><h5>Error</h5><p>Failed to test connection. Please try again.</p></div>';
        });
    }

    window.testUnsavedCredential = function() {
        const countryId = document.getElementById('country_id').value;
        const type = document.getElementById('credential_type').value;
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        const traderId = document.getElementById('trader_id').value;

        if (!countryId || !type || !username || !password) {
            alert('Please fill in Country, Type, Username, and Password before testing.');
            return;
        }
        if (type === 'ftp' && !traderId) {
            alert('Trader ID is required to test FTP credentials.');
            return;
        }

        const btn = document.getElementById('testConnectionBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Testing...';

        const modal = new bootstrap.Modal(document.getElementById('testResultModal'));
        document.getElementById('testResultContent').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Testing connection...</p></div>';
        document.getElementById('testResultHeader').className = 'modal-header';
        modal.show();

        fetch('{{ route("settings.submission-credentials.test-connection") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                country_id: countryId,
                credential_type: type,
                username: username,
                password: password,
                trader_id: traderId,
            }),
        })
        .then(response => response.json())
        .then(data => {
            const header = document.getElementById('testResultHeader');
            const content = document.getElementById('testResultContent');

            if (data.success) {
                header.className = 'modal-header bg-success text-white';
                content.innerHTML = '<div class="text-center"><i class="fas fa-check-circle fa-3x text-success mb-3"></i><h5>Connection Successful!</h5><p>' + data.message + '</p></div>';
            } else {
                header.className = 'modal-header bg-danger text-white';
                content.innerHTML = '<div class="text-center"><i class="fas fa-times-circle fa-3x text-danger mb-3"></i><h5>Connection Failed</h5><p>' + (data.message || 'Unknown error') + '</p></div>';
            }
        })
        .catch(error => {
            console.error('Test error:', error);
            document.getElementById('testResultHeader').className = 'modal-header bg-danger text-white';
            document.getElementById('testResultContent').innerHTML = '<div class="text-center"><i class="fas fa-times-circle fa-3x text-danger mb-3"></i><h5>Error</h5><p>Failed to communicate with server. Please try again.</p></div>';
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plug me-1"></i>Test Connection';
        });
    }

    // Reset form when modal is closed
    document.getElementById('addCredentialModal').addEventListener('hidden.bs.modal', resetForm);
</script>
@endpush
