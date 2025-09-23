@extends('integration::layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h5 class="fs-6 text-muted mb-3">API Configuration</h5>
            <form id="api-config-form" method="POST" action="{{ route('organisations.integration.api.save-configuration', [
                'organisationUid' => $organisation->uid, 
                'integrationUid' => $zohoBooksIntegration->uid, 
                'apiId' => $api->id
            ]) }}">
                @csrf
                <input type="hidden" name="api_id" value="{{ $api->id }}">
                
                {{-- Basic API Info --}}
                <div class="mb-3">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">API Name</label>
                            <input type="text" class="form-control" value="{{ $api->meta_key }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Table Name</label>
                            <select class="form-select" id="table_selector" name="table_name">
                                @foreach($entities as $entity)
                                    <option value="{{ $entity['table_name'] }}" 
                                        {{ $entity['table_name'] == $selectedTable ? 'selected' : '' }}>
                                        {{ $entity['display_name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                @php
                    // Parse API metadata to extract details dynamically
                    $apiDetails = [
                        'method' => 'GET', // Default method
                        'url' => '',
                        'query_params' => [],
                        'headers' => [],
                        'path_params' => []
                    ];
                    
                    try {
                        $metaValue = json_decode($api->meta_value, true);
                        
                        // Extract method
                        if (isset($metaValue['method'])) {
                            $apiDetails['method'] = strtoupper($metaValue['method']);
                        }
                        
                        // Extract URL
                        if (isset($metaValue['api_endpoint'])) {
                            $apiDetails['url'] = $metaValue['api_endpoint'];
                        } elseif (isset($metaValue['url'])) {
                            $apiDetails['url'] = $metaValue['url'];
                        }
                        
                        // Extract query parameters
                        if (isset($metaValue['query_params'])) {
                            $apiDetails['query_params'] = $metaValue['query_params'];
                        } elseif (isset($metaValue['query_parameters'])) {
                            $apiDetails['query_params'] = $metaValue['query_parameters'];
                        }
                        
                        // Extract headers
                        if (isset($metaValue['headers'])) {
                            $apiDetails['headers'] = $metaValue['headers'];
                        } elseif (isset($metaValue['header'])) {
                            // Handle single header or header array
                            if (is_array($metaValue['header'])) {
                                $apiDetails['headers'] = $metaValue['header'];
                            } else {
                                $apiDetails['headers'] = ['Authorization' => $metaValue['header']];
                            }
                        }
                        
                        // Extract path parameters from URL
                        if (!empty($apiDetails['url']) && preg_match_all('/\{(\w+)\}/', $apiDetails['url'], $matches)) {
                            $apiDetails['path_params'] = array_map(function($param) {
                                return ['key' => $param, 'value' => ''];
                            }, $matches[1]);
                        }
                        
                    } catch (\Exception $e) {
                        Log::error('Error parsing API details: ' . $e->getMessage());
                    }
                    
                    // Method badge color mapping
                    $methodColors = [
                        'GET' => 'bg-success',
                        'POST' => 'bg-primary',
                        'PUT' => 'bg-warning',
                        'PATCH' => 'bg-info',
                        'DELETE' => 'bg-danger',
                        'HEAD' => 'bg-secondary',
                        'OPTIONS' => 'bg-dark'
                    ];
                    
                    $methodColor = $methodColors[$apiDetails['method']] ?? 'bg-secondary';
                @endphp
                
                {{-- API Details Card --}}
                <div class="mb-1 p-2">
                    <div class="d-flex align-items-center">
                        <span class="badge {{ $methodColor }} me-2">{{ $apiDetails['method'] }}</span>
                        <h5 class="fs-6 mb-0">{{ $api->meta_key }}</h5>
                    </div>
                    <div>
                        {{-- URL --}}
                        <div class="mb-3">
                            <label class="form-label text-muted small mb-1">URL</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">
                                    <i class="fas fa-link"></i>
                                </span>
                                <input type="text" class="form-control bg-light font-monospace" value="{{ $apiDetails['url'] }}" readonly>
                            </div>
                        </div>
                        
                        <div class="row">
                            {{-- Path Parameters --}}
                            
                            <div class="col-md-6">
                                <label class="form-label text-muted small mb-1">Path Parameters</label>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="50%">Parameter</th>
                                                <th width="50%">Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if(count($apiDetails['path_params']) > 0)
                                                @foreach($apiDetails['path_params'] as $param)
                                                <tr>
                                                    <td>
                                                        <code>{{ $param['key'] }}</code>
                                                    </td>
                                                    <td>
                                                        @if(isset($param['value']) && $param['value'] !== '')
                                                            {{ $param['value'] }}
                                                        @else
                                                            <span class="text-muted fst-italic">Not configured</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td colspan="2" class="text-center text-muted fst-italic">
                                                        No path param is found
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            {{-- Query Parameters --}}
                            <div class="col-md-6">
                                <label class="form-label text-muted small mb-1">Query Parameters</label>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="50%">Parameter</th>
                                                <th width="50%">Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if(count($apiDetails['query_params']) > 0)
                                                @foreach($apiDetails['query_params'] as $key => $value)
                                                <tr>
                                                    <td><code>{{ $key }}</code></td>
                                                    <td>
                                                        @if(is_array($value))
                                                            {{ json_encode($value) }}
                                                        @else
                                                            {{ $value }}
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td colspan="2" class="text-center text-muted fst-italic">
                                                        No query param is found
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Headers --}}
                        @if(count($apiDetails['headers']) > 0)
                        <div>
                            <label class="form-label text-muted small mb-1">Headers</label>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="50%">Header</th>
                                            <th width="50%">Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($apiDetails['headers'] as $key => $value)
                                        <tr>
                                            <td><code>{{ $key }}</code></td>
                                            <td>
                                                @if(is_array($value))
                                                    {{ json_encode($value) }}
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            

                {{-- Body Field Mapping Section --}}
                <div class="mb-3 bg-info-subtle p-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <h5 class="fs-6 text-muted">Request Field Mapping</h5>
                            <small class="text-muted">(Map API request fields to entity table fields)</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="toggle-optional-body-fields">
                            <i class="fas fa-eye"></i> Show Optional
                        </button>
                    </div>
                    <div class="">
                        {{-- Required Body Fields --}}
                        <small class="text-danger mb-2">Required Fields</small>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="required_body_mapping_table">
                                <thead class="table-light">
                                    <tr>
                                        <th width="45%">API Request Field</th>
                                        <th width="45%">Table Field</th>
                                        <th width="10%">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="required_body_field_mapping_container">
                                    @foreach($requiredBodyFields as $bodyField)
                                    @php
                                        $isIdField = false;
                                        $idMappingValue = '';
                                        
                                        // Check if this is the ID field and we have a body_schema_id_key
                                        if ($bodySchemaIdKey && $bodyField === $bodySchemaIdKey) {
                                            $isIdField = true;
                                            $idMappingValue = $selectedTable . '.id';
                                        }
                                    @endphp
                                    <tr class="mapping-row">
                                        <td>
                                            {{ $bodyField }}
                                            <input type="hidden" name="body_mappings[{{ $bodyField }}][body_field]" 
                                                    value="{{ $bodyField }}">
                                            <input type="hidden" name="body_mappings[{{ $bodyField }}][body_field_label]" 
                                                    value="{{ $bodyField }}">
                                        </td>
                                        <td>
                                            @if($isIdField)
                                                {{-- Display disabled field for ID mapping --}}
                                                <input type="text" class="form-control" 
                                                        value="ID (Auto-mapped)" 
                                                        readonly>
                                                <input type="hidden" 
                                                        name="body_mappings[{{ $bodyField }}][project_field]" 
                                                        value="{{ $idMappingValue }}">
                                            @else
                                                {{-- Regular dropdown for other fields --}}
                                                <select class="form-select body-field-select" 
                                                        name="body_mappings[{{ $bodyField }}][project_field]"
                                                        required>
                                                    <option value="">Select table field...</option>
                                                    @foreach($mappableFields as $field)
                                                        <option value="{{ $field['value'] }}"
                                                            {{ isset($existingBodyMappings[$bodyField]) && $existingBodyMappings[$bodyField]['project_field'] == $field['value'] ? 'selected' : '' }}>
                                                            {{ $field['display_name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!$isIdField)
                                                <button type="button" class="btn text-danger btn-sm remove-body-mapping">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @else
                                                <span class="text-info fw-semibold">Auto-mapped</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Optional Body Fields (initially hidden) --}}
                        <div id="optional-body-fields" style="display: none;">
                            <small class="text-muted mb-2 mt-3">Optional Fields</small>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="optional_body_mapping_table">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="45%">API Body Field</th>
                                            <th width="45%">Table Field</th>
                                            <th width="10%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="optional_body_field_mapping_container">
                                        @foreach($optionalBodyFields as $bodyField)
                                        <tr class="mapping-row">
                                            <td>
                                                {{ $bodyField }}
                                                <input type="hidden" name="body_mappings[{{ $bodyField }}][body_field]" 
                                                        value="{{ $bodyField }}">
                                                <input type="hidden" name="body_mappings[{{ $bodyField }}][body_field_label]" 
                                                        value="{{ $bodyField }}">
                                            </td>
                                            <td>
                                                <select class="form-select body-field-select" 
                                                        name="body_mappings[{{ $bodyField }}][project_field]">
                                                    <option value="">Select table field...</option>
                                                    @foreach($mappableFields as $field)
                                                        <option value="{{ $field['value'] }}"
                                                            {{ isset($existingBodyMappings[$bodyField]) && $existingBodyMappings[$bodyField]['project_field'] == $field['value'] ? 'selected' : '' }}>
                                                            {{ $field['display_name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <button type="button" class="btn text-danger btn-sm remove-body-mapping">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Response Field Mapping Section --}}
                <div class="bg-success-subtle p-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                            <h5 class="fs-6 text-muted">Response Field Mapping</h5>
                            <small class="text-muted">(Map entity table fields to API response fields)</small>
                        </div>
                    </div>
                    <div class="">
                        {{-- Response Field Mapping Table --}}
                        <div class="table-responsive">
                            <table class="table table-bordered" id="response_mapping_table">
                                <thead class="table-light">
                                    <tr>
                                        <th width="45%">Table Field</th>
                                        <th width="45%">API Response Field</th>
                                        <th width="10%">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="response_field_mapping_container">
                                    @foreach($mappableFields as $field)
                                    @php
                                        $isIdField = false;
                                        $idMappingValue = '';
                                        
                                        // Check if this is the ID field and we have a response_schema_id_key
                                        if ($responseSchemaIdKey && (
                                            $field['value'] === $selectedTable . '.id' || 
                                            $field['display_name'] === 'Id' ||
                                            str_contains($field['value'], 'id')
                                        )) {
                                            $isIdField = true;
                                            $idMappingValue = $responseSchemaIdKey;
                                        }
                                    @endphp
                                    <tr class="mapping-row">
                                        <td>
                                            {{ $field['display_name'] }}
                                            <input type="hidden" name="mappings[{{ $field['value'] }}][project_field]" 
                                                    value="{{ $field['value'] }}">
                                            <input type="hidden" name="mappings[{{ $field['value'] }}][project_field_label]" 
                                                    value="{{ $field['display_name'] }}">
                                        </td>
                                        <td>
                                            @if($isIdField)
                                                {{-- Display disabled field for ID mapping --}}
                                                <input type="text" class="form-control" 
                                                        value="{{ $idMappingValue }}" 
                                                        readonly
                                                        data-response-field="{{ $idMappingValue }}">
                                                <input type="hidden" 
                                                        name="mappings[{{ $field['value'] }}][response_field]" 
                                                        value="{{ $idMappingValue }}">
                                            @else
                                                {{-- Regular dropdown for other fields --}}
                                                <select class="form-select response-field-select" 
                                                        name="mappings[{{ $field['value'] }}][response_field]"
                                                        {{ isset($existingMappings[$field['value']]) && !empty($existingMappings[$field['value']]['response_field']) ? 'required' : '' }}>
                                                    <option value="">Select response field...</option>
                                                    @foreach($responseFields as $responseField)
                                                        <option value="{{ $responseField }}"
                                                            {{ isset($existingMappings[$field['value']]) && $existingMappings[$field['value']]['response_field'] == $responseField ? 'selected' : '' }}>
                                                            {{ $responseField }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!$isIdField)
                                                <button type="button" class="btn text-danger btn-sm remove-mapping">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @else
                                                <span class="text-info fw-semibold">Auto-mapped</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary gap-2" id="add-meta-key-btn">
                                <i class="fas fs-fw fa-plus"></i> Add Meta Key
                            </button>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-end gap-2 mt-4">
                    <a href="{{ route('organisations.integration.show', [
                        'organisationUid' => $organisation->uid, 
                        'integrationUid' => $zohoBooksIntegration->uid
                    ]) }}" class="btn btn-sm btn-outline-dark">Cancel</a>
                    <button type="button" id="show-mapping-btn" class="btn btn-sm btn-outline-primary">
                        Review & Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Meta Key Modal -->
<div class="modal fade" id="addMetaKeyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6">Add New Meta Key</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="add-meta-key-form">
                    <div class="mb-3">
                        <label class="form-label">Meta Key Name</label>
                        <input type="text" class="form-control" id="meta-key-name" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="save-meta-key-btn">Add</button>
            </div>
        </div>
    </div>
</div>

<!-- Mapping Confirmation Modal -->
<div class="modal fade" id="mappingConfirmationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6">Confirm Field Mapping</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="mb-3">Request Field Mappings:</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50%">API Body Field</th>
                                        <th width="50%">Table Field</th>
                                    </tr>
                                </thead>
                                <tbody id="body-mapping-confirmation-content">
                                    <!-- Body mapping content will be populated here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-3">Response Field Mappings:</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50%">Table Field</th>
                                        <th width="50%">API Response Field</th>
                                    </tr>
                                </thead>
                                <tbody id="response-mapping-confirmation-content">
                                    <!-- Response mapping content will be populated here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="confirm-save-btn">Confirm & Save</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle table selection change
    document.getElementById('table_selector').addEventListener('change', function() {
        const tableName = this.value;
        window.location.href = `{{ url()->current() }}?table_name=${tableName}`;
    });
    
    // Toggle optional body fields
    const toggleOptionalBtn = document.getElementById('toggle-optional-body-fields');
    const optionalBodyFields = document.getElementById('optional-body-fields');
    let optionalFieldsVisible = false;
    
    toggleOptionalBtn.addEventListener('click', function() {
        optionalFieldsVisible = !optionalFieldsVisible;
        optionalBodyFields.style.display = optionalFieldsVisible ? 'block' : 'none';
        toggleOptionalBtn.innerHTML = optionalFieldsVisible ? 
            '<i class="fas fa-eye-slash"></i> Hide Optional' : 
            '<i class="fas fa-eye"></i> Show Optional';
    });
    
    // Remove mapping functionality
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-mapping') || 
            e.target.closest('.remove-mapping')) {
            const row = e.target.closest('tr');
            const select = row.querySelector('select');
            if (select) {
                select.selectedIndex = 0;
            }
        }
        
        if (e.target.classList.contains('remove-body-mapping') || 
            e.target.closest('.remove-body-mapping')) {
            const row = e.target.closest('tr');
            const select = row.querySelector('select');
            if (select) {
                select.selectedIndex = 0;
            }
        }
    });
    
    // Open add meta key modal
    document.getElementById('add-meta-key-btn').addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('addMetaKeyModal'));
        modal.show();
    });
    
    // Save meta key
    document.getElementById('save-meta-key-btn').addEventListener('click', function() {
        const metaKeyName = document.getElementById('meta-key-name').value.trim();
        
        if (!metaKeyName) {
            alert('Please enter a meta key name');
            return;
        }
        
        // Add new row to response mapping table
        const tableName = document.getElementById('table_selector').value;
        const metaTableName = tableName.endsWith('s') ? tableName.slice(0, -1) + '_metas' : tableName + '_metas';
        const fullPath = `${metaTableName}.meta_value->${metaKeyName}`;
        const displayName = metaKeyName.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
        
        // Create new row
        const newRow = document.createElement('tr');
        newRow.className = 'mapping-row';
        newRow.innerHTML = `
            <td>
                ${displayName}
                <input type="hidden" name="mappings[${fullPath}][project_field]" value="${fullPath}">
                <input type="hidden" name="mappings[${fullPath}][project_field_label]" value="${displayName}">
            </td>
            <td>
                <select class="form-select response-field-select" name="mappings[${fullPath}][response_field]" required>
                    <option value="">Select response field...</option>
                    @foreach($responseFields as $responseField)
                        <option value="{{ $responseField }}">{{ $responseField }}</option>
                    @endforeach
                </select>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm remove-mapping">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        
        document.getElementById('response_field_mapping_container').appendChild(newRow);
        
        // Also add to body mapping dropdowns
        const bodySelects = document.querySelectorAll('.body-field-select');
        bodySelects.forEach(select => {
            const option = document.createElement('option');
            option.value = fullPath;
            option.textContent = displayName;
            select.appendChild(option);
        });
        
        // Close modal and reset form
        const modal = bootstrap.Modal.getInstance(document.getElementById('addMetaKeyModal'));
        modal.hide();
        document.getElementById('meta-key-name').value = '';
    });
    
    // Show mapping confirmation modal
    document.getElementById('show-mapping-btn').addEventListener('click', function() {
        // Validate that all required fields have values
        let isValid = true;
        const responseSelects = document.querySelectorAll('.response-field-select');
        const bodySelects = document.querySelectorAll('.body-field-select[required]');
        
        responseSelects.forEach(select => {
            if (select.hasAttribute('required') && !select.value) {
                select.classList.add('is-invalid');
                isValid = false;
            } else {
                select.classList.remove('is-invalid');
            }
        });
        
        bodySelects.forEach(select => {
            if (select.hasAttribute('required') && !select.value) {
                select.classList.add('is-invalid');
                isValid = false;
            } else {
                select.classList.remove('is-invalid');
            }
        });
        
        if (!isValid) {
            alert('Please select values for all required fields.');
            return;
        }
        
        // Prepare mapping display for confirmation - only show mapped fields
        const responseConfirmationContent = document.getElementById('response-mapping-confirmation-content');
        const bodyConfirmationContent = document.getElementById('body-mapping-confirmation-content');
        responseConfirmationContent.innerHTML = '';
        bodyConfirmationContent.innerHTML = '';
        
        let hasResponseMappings = false;
        let hasBodyMappings = false;
        
        // Get all response mapping rows
        const responseMappingRows = document.querySelectorAll('#response_field_mapping_container .mapping-row');
        
        responseMappingRows.forEach(row => {
            const projectFieldLabel = row.querySelector('td:first-child').textContent.trim();
            const responseFieldInput = row.querySelector('select, input[data-response-field]');
            let responseFieldValue = '';
            
            if (responseFieldInput) {
                if (responseFieldInput.tagName === 'SELECT') {
                    responseFieldValue = responseFieldInput.value;
                } else {
                    responseFieldValue = responseFieldInput.value;
                }
            }
            
            // Only add to confirmation if there's a response field selected/available
            if (responseFieldValue) {
                hasResponseMappings = true;
                const responseFieldDisplay = responseFieldInput.tagName === 'SELECT' 
                    ? responseFieldInput.options[responseFieldInput.selectedIndex].text
                    : responseFieldValue;
                    
                const confirmationRow = document.createElement('tr');
                confirmationRow.innerHTML = `
                    <td>${projectFieldLabel}</td>
                    <td>${responseFieldDisplay}</td>
                `;
                responseConfirmationContent.appendChild(confirmationRow);
            }
        });
        
        // Get all body mapping rows
        const bodyMappingRows = document.querySelectorAll('#required_body_field_mapping_container .mapping-row, #optional_body_field_mapping_container .mapping-row');

        bodyMappingRows.forEach(row => {
            const bodyFieldLabel = row.querySelector('td:first-child').textContent.trim();
            const projectFieldInput = row.querySelector('select, input[type="text"][readonly]');
            let projectFieldValue = '';
            let projectFieldDisplay = '';
            
            if (projectFieldInput) {
                if (projectFieldInput.tagName === 'SELECT') {
                    projectFieldValue = projectFieldInput.value;
                    projectFieldDisplay = projectFieldValue ? 
                        projectFieldInput.options[projectFieldInput.selectedIndex].text : '';
                } else if (projectFieldInput.type === 'text' && projectFieldInput.readOnly) {
                    // Handle the auto-mapped ID field case
                    projectFieldValue = projectFieldInput.value;
                    projectFieldDisplay = projectFieldValue;
                }
            }
            
            // Also check for hidden input fields that might contain the actual value
            if (!projectFieldValue) {
                const hiddenInput = row.querySelector('input[type="hidden"][name*="project_field"]');
                if (hiddenInput) {
                    projectFieldValue = hiddenInput.value;
                    // Try to find a display name for this field
                    const fieldOption = document.querySelector(`option[value="${projectFieldValue}"]`);
                    projectFieldDisplay = fieldOption ? fieldOption.textContent : projectFieldValue;
                }
            }
            
            // Only add to confirmation if there's a project field selected/available
            if (projectFieldValue) {
                hasBodyMappings = true;
                
                const confirmationRow = document.createElement('tr');
                confirmationRow.innerHTML = `
                    <td>${bodyFieldLabel}</td>
                    <td>${projectFieldDisplay}</td>
                `;
                bodyConfirmationContent.appendChild(confirmationRow);
            }
        });
        
        // If no fields are mapped, show message instead of table
        if (!hasResponseMappings) {
            responseConfirmationContent.innerHTML = `
                <tr>
                    <td colspan="2" class="text-center text-muted py-3">
                        <i class="fas fa-info-circle me-2"></i>
                        No response fields have been mapped yet.
                    </td>
                </tr>
            `;
        }
        
        if (!hasBodyMappings) {
            bodyConfirmationContent.innerHTML = `
                <tr>
                    <td colspan="2" class="text-center text-muted py-3">
                        <i class="fas fa-info-circle me-2"></i>
                        No body fields have been mapped yet.
                    </td>
                </tr>
            `;
        }
        
        // Enable the confirm button
        document.getElementById('confirm-save-btn').disabled = false;
        
        // Show the confirmation modal
        const modal = new bootstrap.Modal(document.getElementById('mappingConfirmationModal'));
        modal.show();
    });
    
    // Final save confirmation
    document.getElementById('confirm-save-btn').addEventListener('click', function() {
        // Submit the form
        document.getElementById('api-config-form').submit();
        
        // Close the modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('mappingConfirmationModal'));
        modal.hide();
    });
});
</script>
@endsection