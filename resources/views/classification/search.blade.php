@extends('layouts.app')

@section('title', 'Classify Item - BoVi Customs')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-5">
                <h1 class="display-5 fw-bold text-primary">
                    <i class="fas fa-search-dollar me-2"></i>AI Item Classification
                </h1>
                <p class="lead text-muted">
                    Enter any item description and our AI will find the correct customs category
                </p>
            </div>

            <!-- Search Form -->
            <div class="card shadow-sm mb-4">
                <div class="card-body p-4">
                    <form id="classifyForm">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="itemInput" class="form-label fw-semibold">
                                    Item Description
                                </label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-white">
                                        <i class="fas fa-box text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="itemInput" 
                                           name="item"
                                           placeholder="e.g., Car, iPhone, Frozen Chicken, Laptop..."
                                           required
                                           minlength="2"
                                           maxlength="500"
                                           autocomplete="off">
                                </div>
                                <div class="form-text">
                                    Enter the item you want to classify. AI will interpret the meaning even if it doesn't match exactly.
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="countrySelect" class="form-label fw-semibold">
                                    Country (Optional)
                                </label>
                                <select class="form-select form-select-lg" id="countrySelect" name="country_id">
                                    <option value="">All Countries</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 text-center">
                            <button type="submit" class="btn btn-primary btn-lg px-5" id="classifyBtn">
                                <i class="fas fa-robot me-2"></i>Classify Item
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Loading State -->
            <div id="loadingState" class="text-center py-5 d-none">
                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="text-muted">AI is analyzing your item...</h5>
                <p class="text-muted small">This usually takes a few seconds</p>
            </div>

            <!-- Error State -->
            <div id="errorState" class="d-none">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <span id="errorMessage">An error occurred</span>
                </div>
            </div>

            <!-- Result Card -->
            <div id="resultCard" class="d-none">
                <div class="card shadow border-0">
                    <div class="card-header bg-success text-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-check-circle me-2"></i>Classification Result
                            </h5>
                            <span class="badge bg-white text-success fs-6" id="confidenceBadge">
                                95% Confidence
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-4">
                                    <label class="text-muted small text-uppercase">Item Searched</label>
                                    <h4 id="searchedItem" class="mb-0">-</h4>
                                </div>
                                <div class="mb-4">
                                    <label class="text-muted small text-uppercase">Best Matching Category</label>
                                    <h3 class="text-primary mb-1">
                                        <span id="resultCode">-</span>
                                    </h3>
                                    <p id="resultDescription" class="mb-0 text-dark">-</p>
                                </div>
                                <div class="mb-4">
                                    <label class="text-muted small text-uppercase">AI Explanation</label>
                                    <p id="resultExplanation" class="mb-0 fst-italic text-secondary">-</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bg-light rounded p-3 text-center">
                                    <label class="text-muted small text-uppercase d-block mb-2">Duty Rate</label>
                                    <h2 id="resultDutyRate" class="text-success mb-0">-</h2>
                                </div>
                                <div id="alternativesSection" class="mt-3 d-none">
                                    <label class="text-muted small text-uppercase">Alternative Codes</label>
                                    <ul id="alternativesList" class="list-unstyled mb-0 small">
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Classification powered by AI. Please verify for official use.
                            </small>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="resetForm()">
                                <i class="fas fa-redo me-1"></i>New Search
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Example Searches -->
            <div id="examplesSection" class="mt-5">
                <h6 class="text-muted text-uppercase mb-3">
                    <i class="fas fa-lightbulb me-2"></i>Try These Examples
                </h6>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm example-btn" data-item="Car">
                        Car
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm example-btn" data-item="iPhone 15 Pro">
                        iPhone 15 Pro
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm example-btn" data-item="Frozen chicken breast">
                        Frozen chicken breast
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm example-btn" data-item="Cotton t-shirt">
                        Cotton t-shirt
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm example-btn" data-item="LED Television 55 inch">
                        LED Television 55 inch
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm example-btn" data-item="Wooden furniture">
                        Wooden furniture
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm example-btn" data-item="Wine from France">
                        Wine from France
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm example-btn" data-item="Bicycle">
                        Bicycle
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    #resultCard {
        animation: slideUp 0.3s ease-out;
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .example-btn:hover {
        background-color: var(--bs-primary);
        border-color: var(--bs-primary);
        color: white;
    }
    
    #classifyBtn:disabled {
        opacity: 0.7;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('classifyForm');
    const itemInput = document.getElementById('itemInput');
    const countrySelect = document.getElementById('countrySelect');
    const classifyBtn = document.getElementById('classifyBtn');
    const loadingState = document.getElementById('loadingState');
    const errorState = document.getElementById('errorState');
    const errorMessage = document.getElementById('errorMessage');
    const resultCard = document.getElementById('resultCard');
    const examplesSection = document.getElementById('examplesSection');

    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        classifyItem();
    });

    // Example buttons
    document.querySelectorAll('.example-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            itemInput.value = this.dataset.item;
            classifyItem();
        });
    });

    function classifyItem() {
        const item = itemInput.value.trim();
        if (!item || item.length < 2) {
            showError('Please enter at least 2 characters');
            return;
        }

        // Show loading, hide others
        loadingState.classList.remove('d-none');
        errorState.classList.add('d-none');
        resultCard.classList.add('d-none');
        examplesSection.classList.add('d-none');
        classifyBtn.disabled = true;
        classifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Classifying...';

        // Make API request
        fetch('{{ route("classification.classify") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                item: item,
                country_id: countrySelect.value || null
            })
        })
        .then(response => response.json())
        .then(data => {
            loadingState.classList.add('d-none');
            classifyBtn.disabled = false;
            classifyBtn.innerHTML = '<i class="fas fa-robot me-2"></i>Classify Item';

            if (data.success && data.match) {
                showResult(data);
            } else {
                showError(data.error || 'Failed to classify item');
            }
        })
        .catch(error => {
            loadingState.classList.add('d-none');
            classifyBtn.disabled = false;
            classifyBtn.innerHTML = '<i class="fas fa-robot me-2"></i>Classify Item';
            showError('Network error. Please try again.');
            console.error('Error:', error);
        });
    }

    function showResult(data) {
        const match = data.match;
        
        document.getElementById('searchedItem').textContent = data.item;
        document.getElementById('resultCode').textContent = match.code || 'N/A';
        document.getElementById('resultDescription').textContent = match.description || 'No description';
        document.getElementById('resultExplanation').textContent = match.explanation || 'No explanation provided';
        
        // Confidence badge
        const confidence = match.confidence || 0;
        const badge = document.getElementById('confidenceBadge');
        badge.textContent = confidence + '% Confidence';
        
        if (confidence >= 80) {
            badge.className = 'badge bg-white text-success fs-6';
        } else if (confidence >= 50) {
            badge.className = 'badge bg-white text-warning fs-6';
        } else {
            badge.className = 'badge bg-white text-danger fs-6';
        }
        
        // Duty rate
        const dutyRate = match.duty_rate;
        document.getElementById('resultDutyRate').textContent = 
            dutyRate !== null && dutyRate !== undefined ? dutyRate + '%' : 'N/A';
        
        // Alternative codes
        const alternativesSection = document.getElementById('alternativesSection');
        const alternativesList = document.getElementById('alternativesList');
        alternativesList.innerHTML = '';
        
        if (match.alternative_codes && match.alternative_codes.length > 0) {
            match.alternative_codes.forEach(code => {
                const li = document.createElement('li');
                li.className = 'text-muted';
                li.textContent = code;
                alternativesList.appendChild(li);
            });
            alternativesSection.classList.remove('d-none');
        } else {
            alternativesSection.classList.add('d-none');
        }
        
        resultCard.classList.remove('d-none');
    }

    function showError(message) {
        errorMessage.textContent = message;
        errorState.classList.remove('d-none');
        examplesSection.classList.remove('d-none');
    }

    window.resetForm = function() {
        itemInput.value = '';
        resultCard.classList.add('d-none');
        errorState.classList.add('d-none');
        examplesSection.classList.remove('d-none');
        itemInput.focus();
    };
});
</script>
@endsection
