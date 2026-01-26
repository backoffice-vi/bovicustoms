@extends('layouts.app')

@section('title', 'Edit User - Admin')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit User</h5>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Users
                    </a>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.update', $user) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password <small class="text-muted">(leave blank to keep current)</small></label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation">
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required onchange="toggleAgentFields()">
                                <option value="user" {{ old('role', $user->role) == 'user' ? 'selected' : '' }}>User</option>
                                <option value="admin" {{ old('role', $user->role) == 'admin' ? 'selected' : '' }}>Admin</option>
                                <option value="agent" {{ old('role', $user->role) == 'agent' ? 'selected' : '' }}>Agent (Customs Broker)</option>
                            </select>
                            @error('role')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Agents can submit declarations on behalf of multiple organizations.</small>
                        </div>

                        <div id="user-fields">
                            <div class="mb-3">
                                <label for="is_individual" class="form-label">Account Type <span class="text-danger">*</span></label>
                                <select class="form-select @error('is_individual') is-invalid @enderror" id="is_individual" name="is_individual" required>
                                    <option value="1" {{ old('is_individual', $user->is_individual) == '1' ? 'selected' : '' }}>Individual</option>
                                    <option value="0" {{ old('is_individual', $user->is_individual) == '0' ? 'selected' : '' }}>Organization</option>
                                </select>
                                @error('is_individual')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="organization_id" class="form-label">Organization (optional)</label>
                                <select class="form-select @error('organization_id') is-invalid @enderror" id="organization_id" name="organization_id">
                                    <option value="">None</option>
                                    @foreach($organizations as $org)
                                        <option value="{{ $org->id }}" {{ old('organization_id', $user->organization_id) == $org->id ? 'selected' : '' }}>
                                            {{ $org->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('organization_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div id="agent-fields" style="display: none;">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Agent-specific fields. Manage client organizations from the user details page.
                            </div>
                            <div class="mb-3">
                                <label for="agent_company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control @error('agent_company_name') is-invalid @enderror" 
                                       id="agent_company_name" name="agent_company_name" value="{{ old('agent_company_name', $user->agent_company_name) }}">
                                @error('agent_company_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="agent_license_number" class="form-label">License Number</label>
                                <input type="text" class="form-control @error('agent_license_number') is-invalid @enderror" 
                                       id="agent_license_number" name="agent_license_number" value="{{ old('agent_license_number', $user->agent_license_number) }}">
                                @error('agent_license_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="agent_phone" class="form-label">Phone</label>
                                <input type="text" class="form-control @error('agent_phone') is-invalid @enderror" 
                                       id="agent_phone" name="agent_phone" value="{{ old('agent_phone', $user->agent_phone) }}">
                                @error('agent_phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label for="agent_address" class="form-label">Address</label>
                                <textarea class="form-control @error('agent_address') is-invalid @enderror" 
                                          id="agent_address" name="agent_address" rows="2">{{ old('agent_address', $user->agent_address) }}</textarea>
                                @error('agent_address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Update User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleAgentFields() {
    const role = document.getElementById('role').value;
    const userFields = document.getElementById('user-fields');
    const agentFields = document.getElementById('agent-fields');
    
    if (role === 'agent') {
        userFields.style.display = 'none';
        agentFields.style.display = 'block';
    } else {
        userFields.style.display = 'block';
        agentFields.style.display = 'none';
    }
}

// Run on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleAgentFields();
});
</script>
@endpush
