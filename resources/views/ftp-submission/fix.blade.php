@extends('layouts.app')

@section('title', 'Fix and Resubmit')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('declaration-forms.index') }}">Declarations</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ftp-submission.index', $declaration) }}">FTP Submission</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ftp-submission.result', ['declaration' => $declaration->id, 'submission' => $submission->id]) }}">Result</a></li>
                    <li class="breadcrumb-item active">Fix and Resubmit</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 mt-2">
                <i class="fas fa-tools me-2 text-warning"></i>Fix and Resubmit
            </h1>
            <p class="text-muted mb-0">
                Original file: <code>{{ $submission->external_reference }}</code>
            </p>
        </div>
        <div>
            <a href="{{ route('ftp-submission.result', ['declaration' => $declaration->id, 'submission' => $submission->id]) }}"
                class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Result
            </a>
        </div>
    </div>

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="alert alert-info">
        <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>How this works</h6>
        <ol class="mb-0 small">
            <li>Review each rejected line item below.</li>
            <li>Pick the correct tariff from the AI suggestions, or enter one manually (it must already exist in the BVI customs codes table — no padded/heading-only codes).</li>
            <li>When you submit, the system updates the underlying invoice item, recalculates duty, regenerates the T12 file, and uploads it to CAPS along with the B/L and invoice attachments.</li>
        </ol>
    </div>

    <form action="{{ route('ftp-submission.fix.apply', $submission) }}" method="POST">
        @csrf

        @forelse($suggestions as $idx => $suggestion)
            <div class="card mb-3 border-{{ $suggestion['category'] === 'tariff_not_known' || $suggestion['category'] === 'tax_rate_incorrect' ? 'danger' : 'warning' }}">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>
                                Line {{ $suggestion['line_number'] ?? '—' }}:
                                {{ $suggestion['description'] ?: 'Unknown item' }}
                            </strong>
                            <div class="small text-muted">
                                CAPS error:
                                <span class="text-danger">{{ $suggestion['error_message'] }}</span>
                            </div>
                            @if(!empty($suggestion['note']))
                                <div class="small text-muted mt-1">{{ $suggestion['note'] }}</div>
                            @endif
                        </div>
                        <div class="text-end">
                            <span class="badge bg-secondary">{{ str_replace('_', ' ', $suggestion['category']) }}</span>
                            <div class="small mt-1">
                                Current: <code>{{ $suggestion['current_code'] ?: '—' }}</code>
                            </div>
                        </div>
                    </div>
                </div>

                @if(!empty($suggestion['suggestions']))
                <div class="card-body">
                    <input type="hidden" name="fixes[{{ $idx }}][declaration_form_item_id]" value="{{ $suggestion['declaration_form_item_id'] }}">
                    <input type="hidden" name="fixes[{{ $idx }}][invoice_item_id]" value="{{ $suggestion['invoice_item_id'] }}">

                    <div class="mb-2 fw-semibold">Suggested tariffs:</div>
                    @foreach($suggestion['suggestions'] as $sIdx => $s)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio"
                                name="fixes[{{ $idx }}][new_code]"
                                id="fix-{{ $idx }}-{{ $sIdx }}"
                                value="{{ $s['code'] }}"
                                {{ ($sIdx === 0 && $suggestion['auto_apply_safe']) ? 'checked' : '' }}>
                            <label class="form-check-label" for="fix-{{ $idx }}-{{ $sIdx }}">
                                <code class="fw-bold">{{ $s['code'] }}</code>
                                <span class="text-muted">(CAPS: <code>{{ $s['caps_code'] }}</code>)</span>
                                — {{ $s['description'] }}
                                <span class="badge bg-info text-dark ms-1">{{ number_format($s['duty_rate'] ?? 0, 1) }}%</span>
                                <span class="badge bg-light text-dark ms-1">conf {{ number_format($s['confidence'] * 100, 0) }}%</span>
                                <div class="small text-muted ms-4">{{ $s['reasoning'] }}</div>
                            </label>
                        </div>
                    @endforeach

                    <div class="mt-3">
                        <label class="small text-muted mb-1" for="manual-{{ $idx }}">
                            Or enter a different code (must exist exactly in the BVI tariff schedule):
                        </label>
                        <div class="input-group input-group-sm" style="max-width: 360px">
                            <span class="input-group-text">
                                <input class="form-check-input mt-0" type="radio"
                                    name="fixes[{{ $idx }}][new_code]" value=""
                                    id="manual-radio-{{ $idx }}"
                                    onclick="document.getElementById('manual-{{ $idx }}').focus()">
                            </span>
                            <input type="text" class="form-control" id="manual-{{ $idx }}"
                                placeholder="e.g. 1006.301 or 0804.10"
                                onfocus="document.getElementById('manual-radio-{{ $idx }}').checked = true; document.getElementById('manual-radio-{{ $idx }}').value = this.value"
                                oninput="document.getElementById('manual-radio-{{ $idx }}').value = this.value">
                        </div>
                    </div>
                </div>
                @else
                <div class="card-body">
                    <p class="text-muted mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        No automatic suggestions available — this error needs manual review.
                    </p>
                </div>
                @endif
            </div>
        @empty
            <div class="alert alert-secondary">
                No actionable suggestions could be generated. Open the declaration and review the items manually before resubmitting.
            </div>
        @endforelse

        <div class="card">
            <div class="card-body">
                <div class="form-check mb-3">
                    <input type="hidden" name="auto_attach" value="0">
                    <input class="form-check-input" type="checkbox" id="autoAttachToggle" name="auto_attach" value="1" checked>
                    <label class="form-check-label fw-semibold" for="autoAttachToggle">
                        Re-upload attachments (B/L, invoices) with the new T12
                    </label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-redo me-2"></i>Apply Fixes and Resubmit
                    </button>
                    <a href="{{ route('declaration-forms.show', $declaration) }}" class="btn btn-outline-secondary">
                        <i class="fas fa-edit me-2"></i>Edit Declaration Manually
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
