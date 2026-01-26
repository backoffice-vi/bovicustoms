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
                                    Country <span class="text-danger">*</span>
                                </label>
                                <select class="form-select form-select-lg" id="countrySelect" name="country_id" required>
                                    @php
                                        $defaultCountryId = optional($countries->firstWhere('code', 'VG'))->id
                                            ?? optional($countries->first())->id;
                                    @endphp

                                    @forelse($countries as $country)
                                        <option value="{{ $country->id }}" {{ $country->id === $defaultCountryId ? 'selected' : '' }}>
                                            {{ $country->name }}
                                        </option>
                                    @empty
                                        <option value="" selected disabled>No countries available</option>
                                    @endforelse
                                </select>
                                <div class="form-text">
                                    Using British Virgin Islands tariff schedule
                                </div>
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
                                <div class="mt-3">
                                    <label class="text-muted small text-uppercase d-block mb-2">Vector Score</label>
                                    <div class="progress" style="height: 20px;">
                                        <div id="vectorScoreBar" class="progress-bar bg-info" role="progressbar" style="width: 0%">
                                            <span id="vectorScoreText">0%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ambiguity Warning -->
                        <div id="ambiguityWarning" class="alert alert-warning mt-4 d-none">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Multiple Categories Possible:</strong>
                            <span id="ambiguityNote">This item could fall under multiple categories.</span>
                        </div>
                        
                        <!-- Selected Alternative Notice -->
                        <div id="selectedAlternativeNotice" class="alert alert-info mt-4 d-none">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Alternative Selected:</strong> You have selected a different classification. 
                            <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="revertToOriginal()">
                                <i class="fas fa-undo me-1"></i>Revert to Original
                            </button>
                        </div>

                        <!-- Alternatives Section -->
                        <div id="alternativesSection" class="mt-4 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-muted mb-0">
                                    <i class="fas fa-list-alt me-2"></i>Alternative Classifications
                                </h6>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#alternativesModal">
                                    <i class="fas fa-expand me-1"></i>View All
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Code</th>
                                            <th>Description</th>
                                            <th>Duty</th>
                                            <th>Score</th>
                                            <th style="width: 80px">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="alternativesTableBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-brain me-1"></i>
                                Classification powered by Qdrant Vector Search. Please verify for official use.
                            </small>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="resetForm()">
                                <i class="fas fa-redo me-1"></i>New Search
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alternatives Modal -->
            <div class="modal fade" id="alternativesModal" tabindex="-1" aria-labelledby="alternativesModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="alternativesModalLabel">
                                <i class="fas fa-list-alt me-2"></i>All Matching Categories
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted mb-3">
                                <i class="fas fa-info-circle me-1"></i>
                                Click <strong>"Select"</strong> to use a different classification for your item.
                            </p>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 12%">Code</th>
                                            <th style="width: 45%">Description</th>
                                            <th style="width: 12%">Duty Rate</th>
                                            <th style="width: 16%">Match Score</th>
                                            <th style="width: 15%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="allMatchesTableBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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

    // Store original result for revert functionality
    let originalResult = null;
    let currentResult = null;
    let allMatchesData = [];

    function showResult(data) {
        const match = data.match;
        
        // Store original result
        if (!originalResult) {
            originalResult = JSON.parse(JSON.stringify(data));
        }
        currentResult = data;
        
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
        
        // Vector score bar
        const vectorScore = match.vector_score || 0;
        const scoreBar = document.getElementById('vectorScoreBar');
        const scoreText = document.getElementById('vectorScoreText');
        scoreBar.style.width = vectorScore + '%';
        scoreText.textContent = vectorScore.toFixed(1) + '%';
        
        // Color based on score
        if (vectorScore >= 40) {
            scoreBar.className = 'progress-bar bg-success';
        } else if (vectorScore >= 25) {
            scoreBar.className = 'progress-bar bg-info';
        } else {
            scoreBar.className = 'progress-bar bg-warning';
        }
        
        // Ambiguity warning
        const ambiguityWarning = document.getElementById('ambiguityWarning');
        const ambiguityNote = document.getElementById('ambiguityNote');
        if (match.is_ambiguous && match.ambiguity_note) {
            ambiguityNote.textContent = match.ambiguity_note;
            ambiguityWarning.classList.remove('d-none');
        } else {
            ambiguityWarning.classList.add('d-none');
        }
        
        // Alternatives table
        const alternativesSection = document.getElementById('alternativesSection');
        const alternativesTableBody = document.getElementById('alternativesTableBody');
        const allMatchesTableBody = document.getElementById('allMatchesTableBody');
        alternativesTableBody.innerHTML = '';
        allMatchesTableBody.innerHTML = '';
        
        // Combine top match with alternatives for all matches
        allMatchesData = [];
        if (match.code) {
            allMatchesData.push({
                code: match.code,
                description: match.description,
                duty_rate: match.duty_rate,
                score: match.vector_score || 0,
                isTop: true,
                isOriginalTop: true
            });
        }
        
        if (match.alternatives && match.alternatives.length > 0) {
            match.alternatives.forEach(alt => {
                allMatchesData.push({
                    code: alt.code,
                    description: alt.description,
                    duty_rate: alt.duty_rate,
                    score: alt.score || 0,
                    isTop: false,
                    isOriginalTop: false
                });
            });
        }
        
        // Filter out alternatives that have no valid code
        const validAlternatives = match.alternatives ? match.alternatives.filter(alt => alt.code && alt.code.trim() !== '') : [];
        
        if (allMatchesData.length > 1 && validAlternatives.length > 0) {
            // Show alternatives in card (top 3 valid alternatives, not including top match)
            validAlternatives.slice(0, 3).forEach((alt, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><code>${alt.code}</code></td>
                    <td class="small">${(alt.description || 'No description').substring(0, 50)}${alt.description && alt.description.length > 50 ? '...' : ''}</td>
                    <td>${alt.duty_rate !== null && alt.duty_rate !== undefined ? alt.duty_rate + '%' : 'N/A'}</td>
                    <td>
                        <div class="progress" style="height: 15px; min-width: 60px;">
                            <div class="progress-bar bg-secondary" style="width: ${alt.score || 0}%">
                                ${(alt.score || 0).toFixed(0)}%
                            </div>
                        </div>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAlternative(${index + 1})">
                            Select
                        </button>
                    </td>
                `;
                alternativesTableBody.appendChild(tr);
            });
            alternativesSection.classList.remove('d-none');
            
            // Show all matches in modal
            allMatchesData.forEach((m, index) => {
                const tr = document.createElement('tr');
                if (m.isTop) {
                    tr.className = 'table-success';
                }
                tr.id = `modal-row-${index}`;
                tr.innerHTML = `
                    <td>
                        <code class="${m.isTop ? 'fw-bold' : ''}">${m.code || 'N/A'}</code>
                        ${m.isTop ? '<span class="badge bg-success ms-2">Selected</span>' : ''}
                        ${m.isOriginalTop && !m.isTop ? '<span class="badge bg-secondary ms-2">Original</span>' : ''}
                    </td>
                    <td class="small">${m.description || 'No description'}</td>
                    <td>${m.duty_rate !== null && m.duty_rate !== undefined ? m.duty_rate + '%' : 'N/A'}</td>
                    <td>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar ${m.isTop ? 'bg-success' : 'bg-info'}" style="width: ${m.score}%">
                                ${m.score.toFixed(1)}%
                            </div>
                        </div>
                    </td>
                    <td>
                        ${m.isTop ? 
                            '<span class="text-success"><i class="fas fa-check-circle"></i> Current</span>' : 
                            `<button type="button" class="btn btn-sm btn-primary" onclick="selectAlternative(${index})">
                                <i class="fas fa-check me-1"></i>Select
                            </button>`
                        }
                    </td>
                `;
                allMatchesTableBody.appendChild(tr);
            });
        } else {
            // Hide alternatives section if no valid alternatives
            alternativesSection.classList.add('d-none');
        }
        
        // Also filter allMatchesData to only include valid entries for the modal
        allMatchesData = allMatchesData.filter(m => m.code && m.code.trim() !== '');
        
        resultCard.classList.remove('d-none');
    }

    window.selectAlternative = function(index) {
        if (!allMatchesData[index]) return;
        
        const selected = allMatchesData[index];
        
        // Update the main display
        document.getElementById('resultCode').textContent = selected.code || 'N/A';
        document.getElementById('resultDescription').textContent = selected.description || 'No description';
        document.getElementById('resultDutyRate').textContent = 
            selected.duty_rate !== null && selected.duty_rate !== undefined ? selected.duty_rate + '%' : 'N/A';
        
        // Update vector score bar
        const vectorScore = selected.score || 0;
        const scoreBar = document.getElementById('vectorScoreBar');
        const scoreText = document.getElementById('vectorScoreText');
        scoreBar.style.width = vectorScore + '%';
        scoreText.textContent = vectorScore.toFixed(1) + '%';
        
        // Update explanation
        document.getElementById('resultExplanation').textContent = 
            `Alternative classification selected by user. Original AI suggestion was ${originalResult.match.code}. Selected code: ${selected.code} with ${vectorScore.toFixed(1)}% match score.`;
        
        // Update confidence badge
        const badge = document.getElementById('confidenceBadge');
        badge.textContent = 'User Selected';
        badge.className = 'badge bg-white text-primary fs-6';
        
        // Update allMatchesData to mark new selection
        allMatchesData.forEach((m, i) => {
            m.isTop = (i === index);
        });
        
        // Refresh the modal table
        refreshModalTable();
        
        // Show the "alternative selected" notice
        document.getElementById('selectedAlternativeNotice').classList.remove('d-none');
        
        // Hide ambiguity warning since user made a choice
        document.getElementById('ambiguityWarning').classList.add('d-none');
        
        // Close the modal if open
        const modal = bootstrap.Modal.getInstance(document.getElementById('alternativesModal'));
        if (modal) {
            modal.hide();
        }
    }

    function refreshModalTable() {
        const allMatchesTableBody = document.getElementById('allMatchesTableBody');
        allMatchesTableBody.innerHTML = '';
        
        allMatchesData.forEach((m, index) => {
            const tr = document.createElement('tr');
            if (m.isTop) {
                tr.className = 'table-success';
            }
            tr.id = `modal-row-${index}`;
            tr.innerHTML = `
                <td>
                    <code class="${m.isTop ? 'fw-bold' : ''}">${m.code || 'N/A'}</code>
                    ${m.isTop ? '<span class="badge bg-success ms-2">Selected</span>' : ''}
                    ${m.isOriginalTop && !m.isTop ? '<span class="badge bg-secondary ms-2">Original</span>' : ''}
                </td>
                <td class="small">${m.description || 'No description'}</td>
                <td>${m.duty_rate !== null && m.duty_rate !== undefined ? m.duty_rate + '%' : 'N/A'}</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar ${m.isTop ? 'bg-success' : 'bg-info'}" style="width: ${m.score}%">
                            ${m.score.toFixed(1)}%
                        </div>
                    </div>
                </td>
                <td>
                    ${m.isTop ? 
                        '<span class="text-success"><i class="fas fa-check-circle"></i> Current</span>' : 
                        `<button type="button" class="btn btn-sm btn-primary" onclick="selectAlternative(${index})">
                            <i class="fas fa-check me-1"></i>Select
                        </button>`
                    }
                </td>
            `;
            allMatchesTableBody.appendChild(tr);
        });
    }

    window.revertToOriginal = function() {
        if (!originalResult) return;
        
        // Reset allMatchesData
        allMatchesData.forEach((m, i) => {
            m.isTop = m.isOriginalTop;
        });
        
        // Restore original display
        const match = originalResult.match;
        document.getElementById('resultCode').textContent = match.code || 'N/A';
        document.getElementById('resultDescription').textContent = match.description || 'No description';
        document.getElementById('resultExplanation').textContent = match.explanation || 'No explanation provided';
        document.getElementById('resultDutyRate').textContent = 
            match.duty_rate !== null && match.duty_rate !== undefined ? match.duty_rate + '%' : 'N/A';
        
        // Restore vector score
        const vectorScore = match.vector_score || 0;
        const scoreBar = document.getElementById('vectorScoreBar');
        const scoreText = document.getElementById('vectorScoreText');
        scoreBar.style.width = vectorScore + '%';
        scoreText.textContent = vectorScore.toFixed(1) + '%';
        
        // Restore confidence badge
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
        
        // Restore ambiguity warning if applicable
        const ambiguityWarning = document.getElementById('ambiguityWarning');
        if (match.is_ambiguous && match.ambiguity_note) {
            document.getElementById('ambiguityNote').textContent = match.ambiguity_note;
            ambiguityWarning.classList.remove('d-none');
        }
        
        // Hide the notice
        document.getElementById('selectedAlternativeNotice').classList.add('d-none');
        
        // Refresh modal
        refreshModalTable();
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
        // Clear stored results
        originalResult = null;
        currentResult = null;
        allMatchesData = [];
        document.getElementById('selectedAlternativeNotice').classList.add('d-none');
        itemInput.focus();
    };
});
</script>
@endsection
