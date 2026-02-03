@extends('layouts.app')

@section('title', 'T12 Preview - ' . ($declaration->form_number ?? 'Declaration'))

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('declaration-forms.index') }}">Declarations</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ftp-submission.index', $declaration) }}">FTP Submission</a></li>
                    <li class="breadcrumb-item active">Preview</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 mt-2">
                <i class="fas fa-file-code text-primary me-2"></i>T12 File Preview
            </h1>
        </div>
        <a href="{{ route('ftp-submission.index', $declaration) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="row">
        <!-- File Info & Validation -->
        <div class="col-lg-4 mb-4">
            <!-- File Information -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>File Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th>Filename:</th>
                            <td><code>{{ $preview['filename'] }}</code></td>
                        </tr>
                        <tr>
                            <th>Trader ID:</th>
                            <td><code>{{ $preview['trader_id'] }}</code></td>
                        </tr>
                        <tr>
                            <th>Total Lines:</th>
                            <td>{{ $preview['line_count'] }}</td>
                        </tr>
                        <tr>
                            <th>Item Count:</th>
                            <td>{{ $preview['item_count'] }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Validation -->
            <div class="card mb-4">
                <div class="card-header {{ $validation['valid'] ? 'bg-success' : 'bg-warning' }} text-white">
                    <h5 class="mb-0">
                        @if($validation['valid'])
                            <i class="fas fa-check-circle me-2"></i>Validation Passed
                        @else
                            <i class="fas fa-exclamation-triangle me-2"></i>Validation Issues
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    @if(!empty($validation['errors']))
                        <h6 class="text-danger"><i class="fas fa-times-circle me-1"></i>Errors</h6>
                        <ul class="small mb-3">
                            @foreach($validation['errors'] as $error)
                                <li class="text-danger">{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if(!empty($validation['warnings']))
                        <h6 class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Warnings</h6>
                        <ul class="small mb-0">
                            @foreach($validation['warnings'] as $warning)
                                <li class="text-warning">{{ $warning }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if(empty($validation['errors']) && empty($validation['warnings']))
                        <p class="text-success mb-0">
                            <i class="fas fa-check me-1"></i>All validation checks passed.
                        </p>
                    @endif
                </div>
            </div>

            <!-- Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Actions</h5>
                </div>
                <div class="card-body">
                    @if($validation['valid'])
                        <form action="{{ route('ftp-submission.submit', $declaration) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-success btn-lg w-100 mb-2">
                                <i class="fas fa-upload me-2"></i>Submit via FTP
                            </button>
                        </form>
                    @else
                        <button class="btn btn-secondary btn-lg w-100 mb-2" disabled>
                            <i class="fas fa-upload me-2"></i>Fix Errors First
                        </button>
                        <form action="{{ route('ftp-submission.submit', $declaration) }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="force" value="1">
                            <button type="submit" class="btn btn-outline-warning w-100 mb-2">
                                <i class="fas fa-exclamation-triangle me-2"></i>Submit Anyway (with warnings)
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('ftp-submission.download', $declaration) }}" class="btn btn-outline-primary w-100">
                        <i class="fas fa-download me-2"></i>Download T12 File
                    </a>
                </div>
            </div>
        </div>

        <!-- File Content Preview -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-code me-2"></i>T12 File Content</h5>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleView('parsed')">
                            <i class="fas fa-table me-1"></i>Parsed
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleView('raw')">
                            <i class="fas fa-code me-1"></i>Raw
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <!-- Parsed View -->
                    <div id="parsedView">
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th style="width: 80px;">Type</th>
                                        <th>Content</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($preview['lines'] as $index => $line)
                                    <tr>
                                        <td class="text-muted small">{{ $index + 1 }}</td>
                                        <td>
                                            <span class="badge bg-{{ $line['record_type'] === 'R10' ? 'primary' : ($line['record_type'] === 'R30' ? 'success' : ($line['record_type'] === 'R70' ? 'dark' : 'secondary')) }}">
                                                {{ $line['record_type'] }}
                                            </span>
                                            <br>
                                            <small class="text-muted">{{ $line['record_type_name'] }}</small>
                                        </td>
                                        <td>
                                            <code class="small" style="word-break: break-all;">{{ $line['raw'] }}</code>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Raw View -->
                    <div id="rawView" style="display: none;">
                        <pre class="p-3 mb-0 bg-dark text-light" style="max-height: 600px; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;"><code>{{ $preview['raw_content'] }}</code></pre>
                    </div>
                </div>
            </div>

            <!-- Record Type Legend -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-list me-2"></i>T12 Record Types</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <span class="badge bg-primary me-1">R10</span> Header (declaration info)
                        </div>
                        <div class="col-md-4">
                            <span class="badge bg-secondary me-1">R25</span> Container info
                        </div>
                        <div class="col-md-4">
                            <span class="badge bg-secondary me-1">R26</span> Header additional info
                        </div>
                        <div class="col-md-4 mt-2">
                            <span class="badge bg-success me-1">R30</span> Item/record
                        </div>
                        <div class="col-md-4 mt-2">
                            <span class="badge bg-secondary me-1">R40</span> Charges & deductions
                        </div>
                        <div class="col-md-4 mt-2">
                            <span class="badge bg-secondary me-1">R50</span> Tax records
                        </div>
                        <div class="col-md-4 mt-2">
                            <span class="badge bg-secondary me-1">R60</span> Item additional info
                        </div>
                        <div class="col-md-4 mt-2">
                            <span class="badge bg-dark me-1">R70</span> Trailer (line count)
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleView(view) {
    document.getElementById('parsedView').style.display = view === 'parsed' ? 'block' : 'none';
    document.getElementById('rawView').style.display = view === 'raw' ? 'block' : 'none';
}
</script>
@endpush
@endsection
