@extends('layouts.app')

@section('title', 'Classifying Invoice - BoVi Customs')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card border-0 shadow-lg">
                <div class="card-body p-5 text-center">
                    {{-- Status Icon --}}
                    <div id="statusIcon" class="mb-4">
                        <div class="spinner-border text-primary" role="status" style="width: 4rem; height: 4rem;">
                            <span class="visually-hidden">Processing...</span>
                        </div>
                    </div>
                    
                    {{-- Status Title --}}
                    <h2 class="mb-3" id="statusTitle">Classifying Invoice Items</h2>
                    
                    {{-- Progress Bar --}}
                    <div class="progress mb-4" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                             role="progressbar" 
                             id="progressBar"
                             style="width: 0%"
                             aria-valuenow="0" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <span id="progressText">0%</span>
                        </div>
                    </div>
                    
                    {{-- Status Message --}}
                    <p class="text-muted mb-4" id="statusMessage">
                        Starting classification process...
                    </p>
                    
                    {{-- Invoice Info --}}
                    <div class="bg-light rounded-3 p-3 mb-4">
                        <div class="row text-start">
                            <div class="col-6">
                                <small class="text-muted">Invoice Number</small>
                                <div class="fw-semibold">{{ $invoice->invoice_number ?? 'Draft' }}</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Country</small>
                                <div class="fw-semibold">{{ $country->name ?? 'N/A' }}</div>
                            </div>
                        </div>
                        <div class="row text-start mt-2">
                            <div class="col-6">
                                <small class="text-muted">Items</small>
                                <div class="fw-semibold">{{ count($invoice->items ?? []) }}</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Total Amount</small>
                                <div class="fw-semibold">${{ number_format($invoice->total_amount ?? 0, 2) }}</div>
                            </div>
                        </div>
                    </div>
                    
                    {{-- Info Text --}}
                    <div class="alert alert-info border-0" id="infoAlert">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="infoText">
                            AI is analyzing your invoice items and determining the appropriate customs codes. 
                            This page will automatically update when complete.
                        </span>
                    </div>
                    
                    {{-- Action Buttons (hidden initially) --}}
                    <div id="actionButtons" style="display: none;">
                        <a href="#" id="continueBtn" class="btn btn-success btn-lg me-2">
                            <i class="fas fa-check-circle me-2"></i>Review & Assign Codes
                        </a>
                        <a href="{{ route('invoices.create') }}" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-plus me-2"></i>New Invoice
                        </a>
                    </div>
                    
                    {{-- Cancel Link --}}
                    <div class="mt-4" id="cancelSection">
                        <a href="{{ route('invoices.index') }}" class="text-muted small">
                            <i class="fas fa-arrow-left me-1"></i>Back to Invoices
                        </a>
                    </div>
                </div>
            </div>
            
            {{-- What's Happening Card --}}
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-cogs me-2 text-primary"></i>What's Happening?</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex align-items-start mb-3">
                            <span class="badge bg-primary rounded-pill me-3" id="step1Badge">1</span>
                            <div>
                                <strong>Analyzing Item Descriptions</strong>
                                <p class="text-muted small mb-0">Extracting keywords and understanding product categories</p>
                            </div>
                        </li>
                        <li class="d-flex align-items-start mb-3">
                            <span class="badge bg-secondary rounded-pill me-3" id="step2Badge">2</span>
                            <div>
                                <strong>Searching Tariff Database</strong>
                                <p class="text-muted small mb-0">Finding matching HS codes from {{ $country->name ?? 'the country' }}'s tariff schedule</p>
                            </div>
                        </li>
                        <li class="d-flex align-items-start mb-3">
                            <span class="badge bg-secondary rounded-pill me-3" id="step3Badge">3</span>
                            <div>
                                <strong>AI Classification</strong>
                                <p class="text-muted small mb-0">Using AI to select the most appropriate customs code</p>
                            </div>
                        </li>
                        <li class="d-flex align-items-start">
                            <span class="badge bg-secondary rounded-pill me-3" id="step4Badge">4</span>
                            <div>
                                <strong>Checking Restrictions</strong>
                                <p class="text-muted small mb-0">Verifying items against prohibited and restricted goods lists</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.progress {
    border-radius: 15px;
}
.progress-bar {
    border-radius: 15px;
    font-weight: 600;
}
.badge.bg-success {
    background-color: #198754 !important;
}
.card {
    border-radius: 15px;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const invoiceId = {{ $invoice->id }};
    const csrfToken = '{{ csrf_token() }}';
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const statusTitle = document.getElementById('statusTitle');
    const statusMessage = document.getElementById('statusMessage');
    const statusIcon = document.getElementById('statusIcon');
    const infoAlert = document.getElementById('infoAlert');
    const infoText = document.getElementById('infoText');
    const actionButtons = document.getElementById('actionButtons');
    const continueBtn = document.getElementById('continueBtn');
    const cancelSection = document.getElementById('cancelSection');
    
    // Step badges
    const step1Badge = document.getElementById('step1Badge');
    const step2Badge = document.getElementById('step2Badge');
    const step3Badge = document.getElementById('step3Badge');
    const step4Badge = document.getElementById('step4Badge');
    
    let pollInterval = null;
    let classificationStarted = false;
    
    function updateStepBadges(progress) {
        // Update step badges based on progress
        if (progress >= 25) {
            step1Badge.classList.remove('bg-secondary');
            step1Badge.classList.add('bg-success');
            step1Badge.innerHTML = '<i class="fas fa-check"></i>';
        }
        if (progress >= 50) {
            step2Badge.classList.remove('bg-secondary');
            step2Badge.classList.add('bg-success');
            step2Badge.innerHTML = '<i class="fas fa-check"></i>';
        }
        if (progress >= 75) {
            step3Badge.classList.remove('bg-secondary');
            step3Badge.classList.add('bg-success');
            step3Badge.innerHTML = '<i class="fas fa-check"></i>';
        }
        if (progress >= 100) {
            step4Badge.classList.remove('bg-secondary');
            step4Badge.classList.add('bg-success');
            step4Badge.innerHTML = '<i class="fas fa-check"></i>';
        }
        
        // Highlight current step
        if (progress < 25) {
            step1Badge.classList.remove('bg-secondary');
            step1Badge.classList.add('bg-primary');
        } else if (progress < 50) {
            step2Badge.classList.remove('bg-secondary');
            step2Badge.classList.add('bg-primary');
        } else if (progress < 75) {
            step3Badge.classList.remove('bg-secondary');
            step3Badge.classList.add('bg-primary');
        } else if (progress < 100) {
            step4Badge.classList.remove('bg-secondary');
            step4Badge.classList.add('bg-primary');
        }
    }
    
    function showCompleted() {
        // Stop polling
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
        
        // Update UI for completion
        statusIcon.innerHTML = '<i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>';
        statusTitle.textContent = 'Classification Complete!';
        statusTitle.classList.add('text-success');
        statusMessage.textContent = 'All items have been classified. Click below to review and assign codes.';
        
        progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
        progressBar.classList.add('bg-success');
        progressBar.style.width = '100%';
        progressText.textContent = '100%';
        
        infoAlert.classList.remove('alert-info');
        infoAlert.classList.add('alert-success');
        infoText.textContent = 'Classification complete! Review the assigned codes and make any necessary adjustments before finalizing.';
        
        // Show action buttons
        actionButtons.style.display = 'block';
        continueBtn.href = '/invoices/' + invoiceId + '/assign-codes-results';
        cancelSection.style.display = 'none';
        
        // Update all step badges
        updateStepBadges(100);
    }
    
    function showFailed(errorMessage) {
        // Stop polling
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
        
        // Update UI for failure
        statusIcon.innerHTML = '<i class="fas fa-exclamation-circle text-danger" style="font-size: 4rem;"></i>';
        statusTitle.textContent = 'Classification Failed';
        statusTitle.classList.add('text-danger');
        statusMessage.textContent = errorMessage || 'An error occurred during classification. Please try again.';
        
        progressBar.classList.remove('progress-bar-animated', 'progress-bar-striped');
        progressBar.classList.add('bg-danger');
        
        infoAlert.classList.remove('alert-info');
        infoAlert.classList.add('alert-danger');
        infoText.textContent = 'The classification process encountered an error. You can try uploading the invoice again.';
        
        // Show retry button
        actionButtons.innerHTML = `
            <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-lg">
                <i class="fas fa-redo me-2"></i>Try Again
            </a>
        `;
        actionButtons.style.display = 'block';
    }
    
    function checkProgress() {
        fetch('/invoices/' + invoiceId + '/classification-progress')
            .then(response => response.json())
            .then(data => {
                console.log('Progress update:', data);
                
                // If status unknown and we haven't started, don't update UI yet
                if (data.status === 'unknown' && !classificationStarted) {
                    return;
                }
                
                // Update progress bar
                const progress = data.progress || 0;
                progressBar.style.width = progress + '%';
                progressBar.setAttribute('aria-valuenow', progress);
                progressText.textContent = progress + '%';
                
                // Update status message
                if (data.message) {
                    statusMessage.textContent = data.message;
                }
                
                // Update step badges
                updateStepBadges(progress);
                
                // Check status
                if (data.status === 'completed') {
                    showCompleted();
                } else if (data.status === 'failed') {
                    showFailed(data.error || data.message);
                }
            })
            .catch(error => {
                console.error('Error checking progress:', error);
            });
    }
    
    // Simulated progress for sync queue (shows activity while waiting)
    let simulatedProgress = 0;
    let simulationInterval = null;
    const statusMessages = [
        'Analyzing item descriptions...',
        'Searching tariff database...',
        'Matching HS codes...',
        'Running AI classification...',
        'Checking restrictions...',
        'Finalizing classifications...'
    ];
    
    function startSimulatedProgress() {
        simulationInterval = setInterval(() => {
            if (simulatedProgress < 90) {
                simulatedProgress += Math.random() * 8 + 2; // Random increment 2-10%
                if (simulatedProgress > 90) simulatedProgress = 90;
                
                const progress = Math.round(simulatedProgress);
                progressBar.style.width = progress + '%';
                progressText.textContent = progress + '%';
                
                // Update status message based on progress
                const messageIndex = Math.min(Math.floor(progress / 18), statusMessages.length - 1);
                statusMessage.textContent = statusMessages[messageIndex];
                
                // Update step badges
                updateStepBadges(progress);
            }
        }, 800);
    }
    
    function stopSimulatedProgress() {
        if (simulationInterval) {
            clearInterval(simulationInterval);
            simulationInterval = null;
        }
    }
    
    // Function to start classification via AJAX
    function startClassification() {
        if (classificationStarted) return;
        classificationStarted = true;
        
        statusMessage.textContent = 'Starting classification process...';
        
        // Start simulated progress animation
        startSimulatedProgress();
        
        fetch('/invoices/' + invoiceId + '/start-classification', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Classification start response:', data);
            stopSimulatedProgress();
            
            if (data.status === 'completed_sync') {
                // Sync queue - job already completed, redirect to results
                showCompleted();
                setTimeout(() => {
                    window.location.href = '/invoices/' + invoiceId + '/assign-codes-results';
                }, 1500);
            } else if (data.status === 'already_completed') {
                // Already classified before
                showCompleted();
            } else if (data.status === 'started') {
                // Background queue - start polling
                statusMessage.textContent = 'Classification in progress...';
                pollInterval = setInterval(checkProgress, 2000);
            } else if (data.error) {
                showFailed(data.error);
            }
        })
        .catch(error => {
            console.error('Error starting classification:', error);
            stopSimulatedProgress();
            // May already be in progress, try polling
            pollInterval = setInterval(checkProgress, 2000);
        });
    }
    
    // Check if there's pending classification or already in progress
    checkProgress();
    
    // Start classification after a brief delay (ensures page is visible)
    setTimeout(startClassification, 500);
    
    // Also listen for browser notifications if available
    if ('Notification' in window && Notification.permission === 'granted') {
        // Already have permission, no action needed
    } else if ('Notification' in window && Notification.permission !== 'denied') {
        Notification.requestPermission();
    }
});
</script>
@endpush
