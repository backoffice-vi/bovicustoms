@extends('layouts.app')

@section('title', 'Classification Rules')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-cogs text-primary me-2"></i>Classification Rules
                    </h1>
                    <p class="text-muted mb-0">
                        Create custom rules to personalize how items are classified for your organization
                    </p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                    <i class="fas fa-plus me-2"></i>Add New Rule
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

    <!-- Quick Add Instructions Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-lightbulb me-2"></i>Quick Tips
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <h6><span class="badge bg-primary me-2">Keyword</span></h6>
                            <p class="small text-muted mb-0">Match items containing specific words. Use commas for multiple keywords.</p>
                            <p class="small"><em>Example: "supplement, vitamin, nutrient"</em></p>
                        </div>
                        <div class="col-md-3">
                            <h6><span class="badge bg-success me-2">Category</span></h6>
                            <p class="small text-muted mb-0">Classify entire product categories under a specific code.</p>
                            <p class="small"><em>Example: All electronics → 8471</em></p>
                        </div>
                        <div class="col-md-3">
                            <h6><span class="badge bg-warning text-dark me-2">Override</span></h6>
                            <p class="small text-muted mb-0">Force a specific code for exact item matches.</p>
                            <p class="small"><em>Example: "iPhone 15" → 8517.12</em></p>
                        </div>
                        <div class="col-md-3">
                            <h6><span class="badge bg-secondary me-2">Instruction</span></h6>
                            <p class="small text-muted mb-0">General guidance for the AI classifier.</p>
                            <p class="small"><em>Example: "Prefer pharmaceutical codes for health products"</em></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rules List -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Your Classification Rules</h5>
                <span class="badge bg-primary">{{ $rules->count() }} Rules</span>
            </div>
        </div>
        <div class="card-body p-0">
            @if($rules->isEmpty())
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No classification rules yet</h5>
                <p class="text-muted">Create your first rule to customize how items are classified</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRuleModal">
                    <i class="fas fa-plus me-2"></i>Add Your First Rule
                </button>
            </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%">Status</th>
                            <th style="width: 15%">Name</th>
                            <th style="width: 10%">Type</th>
                            <th style="width: 25%">Condition</th>
                            <th style="width: 10%">Target Code</th>
                            <th style="width: 10%">Country</th>
                            <th style="width: 5%">Priority</th>
                            <th style="width: 20%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rules as $rule)
                        <tr class="{{ !$rule->is_active ? 'table-secondary' : '' }}">
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" 
                                           {{ $rule->is_active ? 'checked' : '' }}
                                           onchange="toggleRule({{ $rule->id }})">
                                </div>
                            </td>
                            <td>
                                <strong>{{ $rule->name }}</strong>
                            </td>
                            <td>
                                @switch($rule->rule_type)
                                    @case('keyword')
                                        <span class="badge bg-primary">Keyword</span>
                                        @break
                                    @case('category')
                                        <span class="badge bg-success">Category</span>
                                        @break
                                    @case('override')
                                        <span class="badge bg-warning text-dark">Override</span>
                                        @break
                                    @case('instruction')
                                        <span class="badge bg-secondary">Instruction</span>
                                        @break
                                @endswitch
                            </td>
                            <td>
                                <code class="small">{{ Str::limit($rule->condition, 50) }}</code>
                                @if($rule->instruction)
                                <br><small class="text-muted">{{ Str::limit($rule->instruction, 40) }}</small>
                                @endif
                            </td>
                            <td>
                                @if($rule->target_code)
                                <code class="text-primary fw-bold">{{ $rule->target_code }}</code>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($rule->country)
                                {{ $rule->country->name }}
                                @else
                                <span class="text-muted">All</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">{{ $rule->priority }}</span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        onclick="editRule({{ json_encode($rule) }})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteRule({{ $rule->id }}, '{{ $rule->name }}')">
                                    <i class="fas fa-trash"></i>
                                </button>
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

<!-- Add/Edit Rule Modal -->
<div class="modal fade" id="addRuleModal" tabindex="-1" aria-labelledby="addRuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="ruleForm" method="POST" action="{{ route('settings.classification-rules.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addRuleModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Add Classification Rule
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="ruleName" class="form-label">Rule Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ruleName" name="name" required
                                   placeholder="e.g., Nutritional Supplements Rule">
                        </div>
                        <div class="col-md-4">
                            <label for="rulePriority" class="form-label">Priority</label>
                            <input type="number" class="form-control" id="rulePriority" name="priority" 
                                   value="0" min="0" max="100">
                            <small class="text-muted">Higher = applied first</small>
                        </div>

                        <div class="col-md-6">
                            <label for="ruleType" class="form-label">Rule Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="ruleType" name="rule_type" required onchange="updateFormFields()">
                                <option value="keyword">Keyword Match</option>
                                <option value="category">Category Rule</option>
                                <option value="override">Override</option>
                                <option value="instruction">General Instruction</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="ruleCountry" class="form-label">Apply To Country</label>
                            <select class="form-select" id="ruleCountry" name="country_id">
                                <option value="">All Countries</option>
                                @foreach($countries as $country)
                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12" id="conditionField">
                            <label for="ruleCondition" class="form-label">
                                <span id="conditionLabel">Keywords</span> <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="ruleCondition" name="condition" required
                                   placeholder="e.g., supplement, vitamin, nutrient">
                            <small class="text-muted" id="conditionHelp">
                                Separate multiple keywords with commas
                            </small>
                        </div>

                        <div class="col-md-6" id="targetCodeField">
                            <label for="ruleTargetCode" class="form-label">Target HS Code</label>
                            <input type="text" class="form-control" id="ruleTargetCode" name="target_code"
                                   placeholder="e.g., 2106.90 or 3004.50">
                            <small class="text-muted">The code to assign when rule matches</small>
                        </div>

                        <div class="col-12" id="instructionField">
                            <label for="ruleInstruction" class="form-label">Additional Instructions</label>
                            <textarea class="form-control" id="ruleInstruction" name="instruction" rows="3"
                                      placeholder="e.g., All nutritional supplements should be classified under food preparations unless they contain medicinal ingredients"></textarea>
                            <small class="text-muted">Guidance for the AI classifier</small>
                        </div>
                    </div>

                    <!-- Test Rule Section -->
                    <div class="mt-4 pt-3 border-top">
                        <h6><i class="fas fa-flask me-2"></i>Test Your Rule</h6>
                        <div class="row g-2">
                            <div class="col-md-9">
                                <input type="text" class="form-control" id="testItemInput" 
                                       placeholder="Enter a sample item description to test">
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-outline-secondary w-100" onclick="testRule()">
                                    <i class="fas fa-play me-1"></i>Test
                                </button>
                            </div>
                        </div>
                        <div id="testResult" class="mt-2 d-none">
                            <div class="alert alert-info mb-0 py-2" id="testResultMessage"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i><span id="submitBtnText">Create Rule</span>
                    </button>
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
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Rule</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the rule "<strong id="deleteRuleName"></strong>"?</p>
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
@endsection

@push('scripts')
<script>
    const baseUrl = '{{ route("settings.classification-rules.store") }}';
    const updateUrlBase = '{{ url("settings/classification-rules") }}';
    
    function updateFormFields() {
        const type = document.getElementById('ruleType').value;
        const conditionLabel = document.getElementById('conditionLabel');
        const conditionHelp = document.getElementById('conditionHelp');
        const conditionInput = document.getElementById('ruleCondition');
        const targetCodeField = document.getElementById('targetCodeField');
        const instructionField = document.getElementById('instructionField');

        switch (type) {
            case 'keyword':
                conditionLabel.textContent = 'Keywords';
                conditionHelp.textContent = 'Separate multiple keywords with commas';
                conditionInput.placeholder = 'e.g., supplement, vitamin, nutrient';
                targetCodeField.classList.remove('d-none');
                instructionField.classList.remove('d-none');
                break;
            case 'category':
                conditionLabel.textContent = 'Category Keywords';
                conditionHelp.textContent = 'Words that identify this product category';
                conditionInput.placeholder = 'e.g., electronics, computer, phone';
                targetCodeField.classList.remove('d-none');
                instructionField.classList.remove('d-none');
                break;
            case 'override':
                conditionLabel.textContent = 'Exact Item Name';
                conditionHelp.textContent = 'The exact item description to match';
                conditionInput.placeholder = 'e.g., iPhone 15 Pro Max';
                targetCodeField.classList.remove('d-none');
                instructionField.classList.add('d-none');
                break;
            case 'instruction':
                conditionLabel.textContent = 'Applies To (Keywords)';
                conditionHelp.textContent = 'Keywords that trigger this instruction, or "all" for global';
                conditionInput.placeholder = 'e.g., all, or specific keywords';
                targetCodeField.classList.add('d-none');
                instructionField.classList.remove('d-none');
                break;
        }
    }

    function resetForm() {
        document.getElementById('ruleForm').reset();
        document.getElementById('ruleForm').action = baseUrl;
        document.getElementById('formMethod').value = 'POST';
        document.getElementById('addRuleModalLabel').innerHTML = '<i class="fas fa-plus-circle me-2"></i>Add Classification Rule';
        document.getElementById('submitBtnText').textContent = 'Create Rule';
        document.getElementById('testResult').classList.add('d-none');
        updateFormFields();
    }

    window.editRule = function(rule) {
        resetForm();
        document.getElementById('ruleForm').action = updateUrlBase + '/' + rule.id;
        document.getElementById('formMethod').value = 'PUT';
        document.getElementById('addRuleModalLabel').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Classification Rule';
        document.getElementById('submitBtnText').textContent = 'Update Rule';
        
        document.getElementById('ruleName').value = rule.name;
        document.getElementById('ruleType').value = rule.rule_type;
        document.getElementById('ruleCountry').value = rule.country_id || '';
        document.getElementById('ruleCondition').value = rule.condition;
        document.getElementById('ruleTargetCode').value = rule.target_code || '';
        document.getElementById('ruleInstruction').value = rule.instruction || '';
        document.getElementById('rulePriority').value = rule.priority;
        
        updateFormFields();
        
        new bootstrap.Modal(document.getElementById('addRuleModal')).show();
    }

    window.deleteRule = function(id, name) {
        document.getElementById('deleteRuleName').textContent = name;
        document.getElementById('deleteForm').action = updateUrlBase + '/' + id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    window.toggleRule = function(id) {
        fetch(updateUrlBase + '/' + id + '/toggle', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }

    window.testRule = function() {
        const ruleType = document.getElementById('ruleType').value;
        const condition = document.getElementById('ruleCondition').value;
        const testText = document.getElementById('testItemInput').value;
        const resultDiv = document.getElementById('testResult');
        const resultMessage = document.getElementById('testResultMessage');

        if (!condition || !testText) {
            resultDiv.classList.remove('d-none');
            resultMessage.className = 'alert alert-warning mb-0 py-2';
            resultMessage.textContent = 'Please enter both a condition and test text';
            return;
        }

        fetch('{{ route("settings.classification-rules.test") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({
                rule_type: ruleType,
                condition: condition,
                test_text: testText,
            }),
        })
        .then(response => response.json())
        .then(data => {
            resultDiv.classList.remove('d-none');
            resultMessage.className = data.matches 
                ? 'alert alert-success mb-0 py-2' 
                : 'alert alert-warning mb-0 py-2';
            resultMessage.innerHTML = data.matches 
                ? '<i class="fas fa-check-circle me-2"></i>' + data.message
                : '<i class="fas fa-times-circle me-2"></i>' + data.message;
        });
    }

    // Reset form when modal is closed
    document.getElementById('addRuleModal').addEventListener('hidden.bs.modal', resetForm);
    
    // Initialize form fields
    updateFormFields();
</script>
@endpush
