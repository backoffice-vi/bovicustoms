@extends('layouts.app')

@section('title', 'Edit Country - Admin')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('admin.countries.index') }}">Countries</a></li>
                <li class="breadcrumb-item active">Edit {{ $country->name }}</li>
            </ol>
        </nav>
        <h1 class="h3">
            <i class="fas fa-edit me-2"></i>Edit Country
        </h1>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.countries.update', $country) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="code" class="form-label">Country Code <span class="text-danger">*</span></label>
                                    <input type="text" name="code" id="code" class="form-control @error('code') is-invalid @enderror" 
                                           value="{{ old('code', $country->code) }}" placeholder="e.g., VGB" required maxlength="3" style="text-transform: uppercase;">
                                    <div class="form-text">ISO 3166-1 alpha-3 code (3 letters)</div>
                                    @error('code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="currency_code" class="form-label">Currency Code <span class="text-danger">*</span></label>
                                    <input type="text" name="currency_code" id="currency_code" class="form-control @error('currency_code') is-invalid @enderror" 
                                           value="{{ old('currency_code', $country->currency_code) }}" placeholder="e.g., USD" required maxlength="3" style="text-transform: uppercase;">
                                    <div class="form-text">ISO 4217 currency code (3 letters)</div>
                                    @error('currency_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Country Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $country->name) }}" placeholder="e.g., British Virgin Islands" required maxlength="255">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="flag_emoji" class="form-label">Flag Emoji</label>
                            <input type="text" name="flag_emoji" id="flag_emoji" class="form-control @error('flag_emoji') is-invalid @enderror" 
                                   value="{{ old('flag_emoji', $country->flag_emoji) }}" placeholder="e.g., 🇻🇬" maxlength="10">
                            <div class="form-text">Optional: Country flag emoji for display</div>
                            @error('flag_emoji')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1" {{ old('is_active', $country->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Active</label>
                                <div class="form-text">Inactive countries will not be available for selection</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3"><i class="fas fa-shield-alt me-2"></i>Default Insurance Settings</h5>
                        <p class="text-muted small mb-3">Configure default insurance calculation for shipments to this country. Users can still override these settings per shipment.</p>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="default_insurance_method" class="form-label">Insurance Method</label>
                                    <select name="default_insurance_method" id="default_insurance_method" class="form-select @error('default_insurance_method') is-invalid @enderror">
                                        <option value="percentage" {{ old('default_insurance_method', $country->default_insurance_method) == 'percentage' ? 'selected' : '' }}>Percentage of FOB</option>
                                        <option value="manual" {{ old('default_insurance_method', $country->default_insurance_method) == 'manual' ? 'selected' : '' }}>Manual Entry</option>
                                        <option value="document" {{ old('default_insurance_method', $country->default_insurance_method) == 'document' ? 'selected' : '' }}>From Insurance Certificate</option>
                                    </select>
                                    <div class="form-text">How insurance should be calculated by default</div>
                                    @error('default_insurance_method')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="default_insurance_percentage" class="form-label">Insurance Rate (%)</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" name="default_insurance_percentage" id="default_insurance_percentage" 
                                               class="form-control @error('default_insurance_percentage') is-invalid @enderror" 
                                               value="{{ old('default_insurance_percentage', $country->default_insurance_percentage ?? '1.00') }}" 
                                               min="0" max="100" placeholder="1.00">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="form-text">Percentage of FOB value (common: 0.5% - 1.5%)</div>
                                    @error('default_insurance_percentage')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h5 class="mb-3"><i class="fas fa-upload me-2"></i>FTP Submission Settings</h5>
                        <p class="text-muted small mb-3">Configure FTP submission for this country's customs system (e.g., CAPS for BVI). Organizations will need to enter their own FTP credentials.</p>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="ftp_enabled" id="ftp_enabled" class="form-check-input" value="1" 
                                       {{ old('ftp_enabled', $country->ftp_enabled) ? 'checked' : '' }}
                                       onchange="toggleFtpFields()">
                                <label class="form-check-label" for="ftp_enabled">
                                    <strong>Enable FTP Submission</strong>
                                </label>
                                <div class="form-text">Allow organizations to submit declarations via FTP to this country's customs system</div>
                            </div>
                        </div>

                        <div id="ftpSettingsSection" style="{{ old('ftp_enabled', $country->ftp_enabled) ? '' : 'display: none;' }}">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="ftp_host" class="form-label">FTP Server Host <span class="text-danger">*</span></label>
                                        <input type="text" name="ftp_host" id="ftp_host" 
                                               class="form-control @error('ftp_host') is-invalid @enderror" 
                                               value="{{ old('ftp_host', $country->ftp_host) }}" 
                                               placeholder="e.g., ftp.caps.gov.vg">
                                        <div class="form-text">The FTP server hostname provided by customs</div>
                                        @error('ftp_host')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="ftp_port" class="form-label">FTP Port</label>
                                        <input type="number" name="ftp_port" id="ftp_port" 
                                               class="form-control @error('ftp_port') is-invalid @enderror" 
                                               value="{{ old('ftp_port', $country->ftp_port ?? 21) }}" 
                                               min="1" max="65535">
                                        <div class="form-text">Default: 21</div>
                                        @error('ftp_port')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="ftp_base_path" class="form-label">Base Path</label>
                                        <input type="text" name="ftp_base_path" id="ftp_base_path" 
                                               class="form-control @error('ftp_base_path') is-invalid @enderror" 
                                               value="{{ old('ftp_base_path', $country->ftp_base_path ?? '/') }}" 
                                               placeholder="e.g., /submissions/">
                                        <div class="form-text">Base directory for uploads (trader folders created here)</div>
                                        @error('ftp_base_path')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="ftp_file_format" class="form-label">File Format</label>
                                        <select name="ftp_file_format" id="ftp_file_format" class="form-select @error('ftp_file_format') is-invalid @enderror">
                                            <option value="caps_t12" {{ old('ftp_file_format', $country->ftp_file_format) == 'caps_t12' ? 'selected' : '' }}>CAPS T12 Format</option>
                                        </select>
                                        <div class="form-text">Electronic declaration file format</div>
                                        @error('ftp_file_format')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" name="ftp_passive_mode" id="ftp_passive_mode" class="form-check-input" value="1" 
                                                   {{ old('ftp_passive_mode', $country->ftp_passive_mode ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="ftp_passive_mode">Use Passive Mode</label>
                                            <div class="form-text">Recommended for most firewalls</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="ftp_notification_email" class="form-label">Notification Email</label>
                                        <input type="email" name="ftp_notification_email" id="ftp_notification_email" 
                                               class="form-control @error('ftp_notification_email') is-invalid @enderror" 
                                               value="{{ old('ftp_notification_email', $country->ftp_notification_email) }}" 
                                               placeholder="e.g., customs@gov.vg">
                                        <div class="form-text">Where customs sends submission confirmations</div>
                                        @error('ftp_notification_email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="{{ route('admin.countries.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Update Country
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title"><i class="fas fa-info-circle me-2"></i>Help</h5>
                    <p class="card-text">
                        <strong>Country Code:</strong> Use the ISO 3166-1 alpha-3 code (e.g., VGB for British Virgin Islands, USA for United States).
                    </p>
                    <p class="card-text">
                        <strong>Currency Code:</strong> Use the ISO 4217 code (e.g., USD for US Dollar, EUR for Euro).
                    </p>
                    <p class="card-text mb-0">
                        <strong>Flag Emoji:</strong> You can copy flag emojis from websites like emojipedia.org.
                    </p>
                </div>
            </div>

            <div class="card mt-3 border-warning">
                <div class="card-body">
                    <h5 class="card-title text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Caution</h5>
                    <p class="card-text mb-0">
                        Changes to this country may affect customs codes and other related data. Deactivating a country will hide it from selection in other parts of the system.
                    </p>
                </div>
            </div>

            @if($country->customsCodes()->count() > 0 || $country->organizations()->count() > 0)
            <div class="card mt-3 border-info">
                <div class="card-body">
                    <h5 class="card-title text-info"><i class="fas fa-link me-2"></i>Related Data</h5>
                    <ul class="mb-0">
                        @if($country->customsCodes()->count() > 0)
                            <li>{{ $country->customsCodes()->count() }} customs code(s)</li>
                        @endif
                        @if($country->organizations()->count() > 0)
                            <li>{{ $country->organizations()->count() }} organization(s)</li>
                        @endif
                    </ul>
                </div>
            </div>
            @endif

            @if($country->isFtpEnabled())
            <div class="card mt-3 border-success">
                <div class="card-body">
                    <h5 class="card-title text-success"><i class="fas fa-upload me-2"></i>FTP Enabled</h5>
                    <p class="card-text mb-0">
                        FTP submission is enabled for this country. Organizations can configure their own FTP credentials to submit declarations.
                    </p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleFtpFields() {
    const ftpEnabled = document.getElementById('ftp_enabled').checked;
    const ftpSection = document.getElementById('ftpSettingsSection');
    ftpSection.style.display = ftpEnabled ? 'block' : 'none';
}
</script>
@endpush
@endsection
