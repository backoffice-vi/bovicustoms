@extends('layouts.app')

@section('title', 'Edit Field Mapping - ' . $mapping->web_field_label)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.web-form-targets.index') }}">Web Form Targets</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.web-form-targets.show', $webFormTarget) }}">{{ $webFormTarget->name }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.web-form-targets.pages.show', [$webFormTarget, $page]) }}">{{ $page->name }}</a></li>
                    <li class="breadcrumb-item active">Edit Mapping</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 mt-2">Edit Field Mapping: {{ $mapping->web_field_label }}</h1>
        </div>
        <div>
            <form action="{{ route('admin.web-form-targets.mappings.destroy', [$webFormTarget, $page, $mapping]) }}" 
                  method="POST" 
                  onsubmit="return confirm('Are you sure? This will delete the mapping and all its dropdown values.')"
                  class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <form action="{{ route('admin.web-form-targets.mappings.update', [$webFormTarget, $page, $mapping]) }}" method="POST">
                @csrf
                @method('PUT')

                <!-- Web Form Field -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Web Form Field (Target)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="web_field_label" class="form-label">Field Label <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('web_field_label') is-invalid @enderror" 
                                       id="web_field_label" name="web_field_label" value="{{ old('web_field_label', $mapping->web_field_label) }}" required>
                                @error('web_field_label')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="field_type" class="form-label">Field Type <span class="text-danger">*</span></label>
                                <select class="form-select @error('field_type') is-invalid @enderror" 
                                        id="field_type" name="field_type" required>
                                    <option value="text" {{ old('field_type', $mapping->field_type) === 'text' ? 'selected' : '' }}>Text Input</option>
                                    <option value="select" {{ old('field_type', $mapping->field_type) === 'select' ? 'selected' : '' }}>Dropdown</option>
                                    <option value="checkbox" {{ old('field_type', $mapping->field_type) === 'checkbox' ? 'selected' : '' }}>Checkbox</option>
                                    <option value="radio" {{ old('field_type', $mapping->field_type) === 'radio' ? 'selected' : '' }}>Radio Button</option>
                                    <option value="date" {{ old('field_type', $mapping->field_type) === 'date' ? 'selected' : '' }}>Date</option>
                                    <option value="textarea" {{ old('field_type', $mapping->field_type) === 'textarea' ? 'selected' : '' }}>Text Area</option>
                                    <option value="number" {{ old('field_type', $mapping->field_type) === 'number' ? 'selected' : '' }}>Number</option>
                                    <option value="hidden" {{ old('field_type', $mapping->field_type) === 'hidden' ? 'selected' : '' }}>Hidden</option>
                                </select>
                                @error('field_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="web_field_name" class="form-label">Field Name Attribute</label>
                                <input type="text" class="form-control @error('web_field_name') is-invalid @enderror" 
                                       id="web_field_name" name="web_field_name" value="{{ old('web_field_name', $mapping->web_field_name) }}">
                                @error('web_field_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="web_field_id" class="form-label">Field ID</label>
                                <input type="text" class="form-control @error('web_field_id') is-invalid @enderror" 
                                       id="web_field_id" name="web_field_id" value="{{ old('web_field_id', $mapping->web_field_id) }}">
                                @error('web_field_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="web_field_selectors" class="form-label">CSS Selectors <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('web_field_selectors') is-invalid @enderror" 
                                   id="web_field_selectors" name="web_field_selectors" 
                                   value="{{ old('web_field_selectors', is_array($mapping->web_field_selectors) ? implode(', ', $mapping->web_field_selectors) : $mapping->web_field_selectors) }}" required>
                            <small class="text-muted">Comma-separated list of CSS selectors to try (in order)</small>
                            @error('web_field_selectors')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="section" class="form-label">Section</label>
                                <input type="text" class="form-control @error('section') is-invalid @enderror" 
                                       id="section" name="section" value="{{ old('section', $mapping->section) }}">
                                @error('section')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="tab_order" class="form-label">Tab Order</label>
                                <input type="number" class="form-control @error('tab_order') is-invalid @enderror" 
                                       id="tab_order" name="tab_order" value="{{ old('tab_order', $mapping->tab_order) }}" min="1">
                                @error('tab_order')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="max_length" class="form-label">Max Length</label>
                                <input type="number" class="form-control @error('max_length') is-invalid @enderror" 
                                       id="max_length" name="max_length" value="{{ old('max_length', $mapping->max_length) }}" min="1">
                                @error('max_length')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_required" name="is_required" value="1"
                                           {{ old('is_required', $mapping->is_required) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_required">
                                        Required Field
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                           {{ old('is_active', $mapping->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        Active
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Local Data Source -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Local Data Source</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="local_field" class="form-label">Local Field Path</label>
                            <select class="form-select @error('local_field') is-invalid @enderror" 
                                    id="local_field" name="local_field">
                                <option value="">-- Select Local Field --</option>
                                @foreach($localFields as $group => $fields)
                                    <optgroup label="{{ $group }}">
                                        @foreach($fields as $value => $label)
                                            <option value="{{ $value }}" {{ old('local_field', $mapping->local_field) === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <small class="text-muted">Select the local data field to map to this web field</small>
                            @error('local_field')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <hr>
                        <p class="text-muted small">Or specify manually:</p>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="local_table" class="form-label">Table</label>
                                <select class="form-select @error('local_table') is-invalid @enderror" 
                                        id="local_table" name="local_table">
                                    <option value="">-</option>
                                    <option value="declaration_forms" {{ old('local_table', $mapping->local_table) === 'declaration_forms' ? 'selected' : '' }}>declaration_forms</option>
                                    <option value="shipments" {{ old('local_table', $mapping->local_table) === 'shipments' ? 'selected' : '' }}>shipments</option>
                                    <option value="invoices" {{ old('local_table', $mapping->local_table) === 'invoices' ? 'selected' : '' }}>invoices</option>
                                    <option value="trade_contacts" {{ old('local_table', $mapping->local_table) === 'trade_contacts' ? 'selected' : '' }}>trade_contacts</option>
                                    <option value="countries" {{ old('local_table', $mapping->local_table) === 'countries' ? 'selected' : '' }}>countries</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="local_column" class="form-label">Column</label>
                                <input type="text" class="form-control @error('local_column') is-invalid @enderror" 
                                       id="local_column" name="local_column" value="{{ old('local_column', $mapping->local_column) }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="local_relation" class="form-label">Relation</label>
                                <input type="text" class="form-control @error('local_relation') is-invalid @enderror" 
                                       id="local_relation" name="local_relation" value="{{ old('local_relation', $mapping->local_relation) }}">
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="static_value" class="form-label">Static Value</label>
                                <input type="text" class="form-control @error('static_value') is-invalid @enderror" 
                                       id="static_value" name="static_value" value="{{ old('static_value', $mapping->static_value) }}">
                                <small class="text-muted">Overrides local data source</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="default_value" class="form-label">Default Value</label>
                                <input type="text" class="form-control @error('default_value') is-invalid @enderror" 
                                       id="default_value" name="default_value" value="{{ old('default_value', $mapping->default_value) }}">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Notes</h5>
                    </div>
                    <div class="card-body">
                        <textarea class="form-control @error('notes') is-invalid @enderror" 
                                  id="notes" name="notes" rows="2">{{ old('notes', $mapping->notes) }}</textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-between mb-5">
                    <a href="{{ route('admin.web-form-targets.pages.show', [$webFormTarget, $page]) }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <!-- Dropdown Values Section -->
            @if($mapping->field_type === 'select')
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Dropdown Values ({{ $mapping->dropdownValues->count() }})</h5>
                    <!-- Button could trigger a modal to add new value manually -->
                </div>
                <div class="card-body p-0">
                    @if($mapping->dropdownValues->count() > 0)
                        <div class="table-responsive" style="max-height: 500px;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Code (Value)</th>
                                        <th>Label</th>
                                        <th>Local Matches</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($mapping->dropdownValues as $value)
                                    <tr>
                                        <td><code>{{ $value->option_value }}</code></td>
                                        <td class="small">{{ $value->option_label }}</td>
                                        <td class="small text-muted">
                                            @if($value->local_matches)
                                                @foreach($value->local_matches as $match)
                                                    <span class="badge bg-light text-dark border">{{ $match }}</span>
                                                @endforeach
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-3 text-center text-muted">
                            <p class="mb-0">No dropdown values defined.</p>
                            <small>Import via artisan command or add manually.</small>
                        </div>
                    @endif
                </div>
            </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Help</h5>
                </div>
                <div class="card-body">
                    <h6>CSS Selectors</h6>
                    <p class="small text-muted">
                        Provide multiple selectors separated by commas. The system will try each one in order until it finds a match.
                    </p>
                    <ul class="small text-muted">
                        <li><code>[name="x"]</code> - by name attribute</li>
                        <li><code>#id</code> - by ID</li>
                        <li><code>.class</code> - by class</li>
                        <li><code>input[type="text"]</code> - by type</li>
                    </ul>

                    <h6>Data Source Priority</h6>
                    <ol class="small text-muted">
                        <li>Static Value (if set)</li>
                        <li>Local Field Path</li>
                        <li>Table/Column/Relation</li>
                        <li>Default Value</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
