@extends('layouts.app')

@section('title', 'CAPS Import - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-cloud-download-alt me-2"></i>CAPS Import</h2>
        <a href="{{ route('legacy-clearances.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-archive me-2"></i>Legacy Clearances
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show">
            <i class="fas fa-info-circle me-2"></i>{{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(!$bviCountry)
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            British Virgin Islands country is not configured in the system. CAPS Import is only available for BVI.
        </div>
    @else

    {{-- CAPS Connection & Credentials --}}
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <strong><i class="fas fa-plug me-2"></i>CAPS Connection — British Virgin Islands</strong>
            <div class="small opacity-75">Enter your CAPS portal credentials to download your trade declarations and supporting documents.</div>
        </div>
        <div class="card-body">
            @php
                $savedUsername = $capsCredential?->getCapsCredentials()['username'] ?? '';
            @endphp
            <form method="POST" action="{{ route('caps-import.save-credentials') }}">
                @csrf
                <div class="row align-items-end g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">CAPS URL</label>
                        <input type="text" class="form-control" value="https://caps.gov.vg" disabled>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Username (Email)</label>
                        <input type="email" name="caps_username" class="form-control" 
                               value="{{ $savedUsername }}"
                               placeholder="your-email@company.com" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" name="caps_password" class="form-control" 
                               placeholder="{{ $capsCredential ? '••••••••' : 'Enter password' }}" 
                               {{ $capsCredential ? '' : 'required' }}>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fas fa-save me-1"></i>{{ $capsCredential ? 'Update Credentials' : 'Save Credentials' }}
                        </button>
                    </div>
                </div>
            </form>

            @if($capsConfigured)
                <hr class="my-3">
                <form method="POST" action="{{ route('caps-import.fetch') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sync me-1"></i>Fetch TD List from CAPS
                    </button>
                </form>
            @endif

            @if($capsConfigured)
                <div class="mt-3 d-flex align-items-center gap-2">
                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Connected</span>
                    <span class="text-muted small">
                        Timeout: {{ config('services.caps.download_timeout', 60) }}s | Max retries: {{ config('services.caps.max_retries', 3) }}
                    </span>
                </div>
            @else
                <div class="alert alert-warning mt-3 mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Enter your CAPS username and password above, then click Save. Once saved, you can fetch your TD list.
                </div>
            @endif
        </div>
    </div>

    {{-- Status Summary --}}
    @if($statusCounts['total'] > 0)
    <div class="row g-3 mb-4">
        <div class="col">
            <div class="card text-center border-secondary">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold">{{ $statusCounts['total'] }}</div>
                    <div class="small text-muted">Total TDs</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center border-warning">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-warning">{{ $statusCounts['pending'] }}</div>
                    <div class="small text-muted">Pending</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center border-info">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-info">{{ $statusCounts['downloaded'] }}</div>
                    <div class="small text-muted">Downloaded</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center border-primary">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-primary">{{ $statusCounts['imported'] }}</div>
                    <div class="small text-muted">Imported</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center border-success">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-success">{{ $statusCounts['completed'] }}</div>
                    <div class="small text-muted">Completed</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card text-center border-danger">
                <div class="card-body py-2">
                    <div class="fs-4 fw-bold text-danger">{{ $statusCounts['failed'] }}</div>
                    <div class="small text-muted">Failed</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bulk Actions --}}
    <div class="card mb-4">
        <div class="card-body d-flex gap-2 flex-wrap">
            <form method="POST" action="{{ route('caps-import.download-all') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-primary" @if($statusCounts['pending'] == 0) disabled @endif>
                    <i class="fas fa-download me-1"></i>Download All Pending ({{ $statusCounts['pending'] }})
                </button>
            </form>

            <form method="POST" action="{{ route('caps-import.import-all') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-success" @if($statusCounts['downloaded'] == 0) disabled @endif>
                    <i class="fas fa-database me-1"></i>Import All Downloaded ({{ $statusCounts['downloaded'] }})
                </button>
            </form>

            <form method="POST" action="{{ route('caps-import.process-invoices') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-info" @if($statusCounts['imported'] == 0) disabled @endif
                    onclick="return confirm('This will process invoice PDFs using AI extraction. Continue?')">
                    <i class="fas fa-file-invoice me-1"></i>Process All Invoices ({{ $statusCounts['imported'] }})
                </button>
            </form>

            <form method="POST" action="{{ route('caps-import.retry-failed') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-danger" @if($statusCounts['failed'] == 0) disabled @endif>
                    <i class="fas fa-redo me-1"></i>Retry Failed ({{ $statusCounts['failed'] }})
                </button>
            </form>
        </div>
    </div>
    @endif

    {{-- TD List --}}
    @if($imports->count() > 0)
    <div class="card">
        <div class="card-header">
            <strong><i class="fas fa-list me-2"></i>Trade Declarations</strong>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>TD Number</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Attachments</th>
                        <th>Retries</th>
                        <th>Downloaded</th>
                        <th>Imported</th>
                        <th>Invoices Processed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($imports as $import)
                    <tr>
                        <td class="fw-semibold font-monospace">{{ $import->td_number }}</td>
                        <td>
                            <span class="badge bg-{{ \App\Models\CapsImport::statusBadgeClass($import->status) }}">
                                {{ \App\Models\CapsImport::statusLabel($import->status) }}
                            </span>
                        </td>
                        <td>{{ $import->items_count }}</td>
                        <td>
                            @php $attCount = count($import->attachments ?? []); @endphp
                            {{ $attCount }}
                            @if($attCount > 0)
                                <i class="fas fa-paperclip text-muted ms-1" title="{{ implode(', ', $import->attachments ?? []) }}"></i>
                            @endif
                        </td>
                        <td>
                            @if($import->retry_count > 0)
                                <span class="text-warning">{{ $import->retry_count }}</span>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $import->downloaded_at?->format('M j, Y H:i') ?? '—' }}</td>
                        <td class="small text-muted">{{ $import->imported_at?->format('M j, Y H:i') ?? '—' }}</td>
                        <td class="small text-muted">{{ $import->invoices_processed_at?->format('M j, Y H:i') ?? '—' }}</td>
                        <td>
                            <div class="d-flex gap-1">
                                @if($import->status === \App\Models\CapsImport::STATUS_FAILED || $import->status === \App\Models\CapsImport::STATUS_PENDING)
                                    <form method="POST" action="{{ route('caps-import.download-single', $import) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary" title="Download / Retry">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </form>
                                @endif

                                @if($import->status === \App\Models\CapsImport::STATUS_IMPORTED)
                                    <form method="POST" action="{{ route('caps-import.process-invoices-single', $import) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-info" title="Process Invoices">
                                            <i class="fas fa-file-invoice"></i>
                                        </button>
                                    </form>
                                @endif

                                @if($import->shipment_id)
                                    <a href="{{ route('legacy-clearances.shipments.show', $import->shipment_id) }}" class="btn btn-sm btn-outline-secondary" title="View Shipment">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                @endif
                            </div>

                            @if($import->error_message)
                                <div class="small text-danger mt-1" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $import->error_message }}">
                                    {{ $import->error_message }}
                                </div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @elseif($statusCounts['total'] == 0)
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-cloud-download-alt fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No CAPS imports yet</h5>
            <p class="text-muted">Click "Fetch TD List" to connect to CAPS and discover your trade declarations.</p>
        </div>
    </div>
    @endif

    @endif {{-- end bviCountry check --}}
</div>
@endsection
