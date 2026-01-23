@extends('layouts.app')

@section('title', 'AI Settings - Admin')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-cog me-2"></i>AI Settings
        </h1>
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Admin
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <!-- Claude API Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-robot me-2"></i>Claude API Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="claude_api_key" class="form-label">
                                Claude API Key
                                <a href="https://console.anthropic.com/" target="_blank" class="text-decoration-none small">
                                    <i class="fas fa-external-link-alt ms-1"></i> Get API Key
                                </a>
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control @error('claude_api_key') is-invalid @enderror" 
                                       id="claude_api_key" name="claude_api_key" 
                                       value="{{ old('claude_api_key', $settings['claude_api_key']) }}"
                                       placeholder="sk-ant-...">
                                <button class="btn btn-outline-secondary" type="button" id="toggleClaudeKey">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            @error('claude_api_key')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Used for document analysis and item classification
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="claude_model" class="form-label">Claude Model</label>
                            <input type="text" class="form-control @error('claude_model') is-invalid @enderror" 
                                   id="claude_model" name="claude_model" 
                                   value="{{ old('claude_model', $settings['claude_model']) }}"
                                   placeholder="e.g., claude-sonnet-4-20250514">
                            @error('claude_model')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Common models: <strong>claude-sonnet-4-20250514</strong> (Sonnet 4.5), 
                                <strong>claude-3-5-sonnet-20241022</strong> (3.5 Sonnet), 
                                <strong>claude-3-opus-20240229</strong> (3 Opus)
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="claude_max_tokens" class="form-label">Max Output Tokens</label>
                            <input type="number" class="form-control @error('claude_max_tokens') is-invalid @enderror" 
                                   id="claude_max_tokens" name="claude_max_tokens" 
                                   value="{{ old('claude_max_tokens', $settings['claude_max_tokens']) }}"
                                   min="100" max="16384">
                            @error('claude_max_tokens')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Maximum response length (Claude 3.x: 8192, Claude 4.x: check model specs)
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="claude_max_context_tokens" class="form-label">Max Context Window Tokens</label>
                            <input type="number" class="form-control @error('claude_max_context_tokens') is-invalid @enderror" 
                                   id="claude_max_context_tokens" name="claude_max_context_tokens" 
                                   value="{{ old('claude_max_context_tokens', config('services.claude.max_context_tokens', 200000)) }}"
                                   min="100000" max="500000">
                            @error('claude_max_context_tokens')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Max input tokens the model can handle (200k for most models)
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="claude_chunk_size" class="form-label">Document Chunk Size (tokens)</label>
                            <input type="number" class="form-control @error('claude_chunk_size') is-invalid @enderror" 
                                   id="claude_chunk_size" name="claude_chunk_size" 
                                   value="{{ old('claude_chunk_size', config('services.claude.chunk_size', 95000)) }}"
                                   min="10000" max="200000">
                            @error('claude_chunk_size')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Split large documents into chunks of this size (default: 95,000 to stay under 100k)
                            </small>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-outline-primary" id="testClaudeBtn">
                                <i class="fas fa-plug me-1"></i> Test Connection
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- OpenAI Settings (Optional) -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-brain me-2"></i>OpenAI Configuration (Optional)</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="openai_api_key" class="form-label">
                                OpenAI API Key
                                <a href="https://platform.openai.com/api-keys" target="_blank" class="text-decoration-none small">
                                    <i class="fas fa-external-link-alt ms-1"></i> Get API Key
                                </a>
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control @error('openai_api_key') is-invalid @enderror" 
                                       id="openai_api_key" name="openai_api_key" 
                                       value="{{ old('openai_api_key', $settings['openai_api_key']) }}"
                                       placeholder="sk-...">
                                <button class="btn btn-outline-secondary" type="button" id="toggleOpenAIKey">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            @error('openai_api_key')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Reserved for future features
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar with Info -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Configuration Info</h6>
                </div>
                <div class="card-body">
                    <h6>Claude API</h6>
                    <p class="small mb-3">
                        Claude is used for:
                    </p>
                    <ul class="small mb-3">
                        <li>Analyzing uploaded law documents</li>
                        <li>Extracting customs categories</li>
                        <li>Classifying invoice items</li>
                    </ul>
                    
                    <h6>Security</h6>
                    <p class="small mb-0">
                        API keys are stored in your <code>.env</code> file and never exposed to users.
                    </p>
                </div>
            </div>

            <div class="card bg-warning bg-opacity-10 border-warning">
                <div class="card-body">
                    <h6 class="text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Important</h6>
                    <p class="small mb-0">
                        After updating settings, the configuration cache is automatically cleared. 
                        Changes take effect immediately.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility for Claude API key
    document.getElementById('toggleClaudeKey').addEventListener('click', function() {
        const input = document.getElementById('claude_api_key');
        const icon = this.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });

    // Toggle password visibility for OpenAI API key
    document.getElementById('toggleOpenAIKey').addEventListener('click', function() {
        const input = document.getElementById('openai_api_key');
        const icon = this.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });

    // Test Claude connection
    document.getElementById('testClaudeBtn').addEventListener('click', function() {
        const btn = this;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Testing...';

        const apiKey = document.getElementById('claude_api_key').value;

        fetch('{{ route("admin.settings.test-claude") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ api_key: apiKey })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ ' + data.message);
            } else {
                alert('❌ ' + data.message);
            }
        })
        .catch(error => {
            alert('❌ Connection test failed: ' + error.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });

    // Handle form submissions via AJAX to avoid connection reset issues
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    submitBtn.innerHTML = '<i class="fas fa-check me-1"></i> Saved! Reloading...';
                    submitBtn.classList.remove('btn-primary');
                    submitBtn.classList.add('btn-success');
                    
                    // Wait for server to restart, then reload
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    alert('Error: ' + (data.message || 'Failed to save settings'));
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                // Connection may have been reset due to server restart - this is expected
                submitBtn.innerHTML = '<i class="fas fa-check me-1"></i> Saved! Reloading...';
                submitBtn.classList.remove('btn-primary');
                submitBtn.classList.add('btn-success');
                
                // Wait for server to restart, then reload
                setTimeout(function() {
                    window.location.reload();
                }, 2500);
            });
        });
    });
});
</script>
@endsection
