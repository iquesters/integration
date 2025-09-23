@extends('integration::layouts.general-configuration')

@section('general-configuration-content')
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fs-6 text-muted">Zoho Books Integration Status</h5>
        </div>

        <!-- Configuration Details -->
        <div class="row">
            <div class="col-md-6">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="text-muted">Selected APIs:</h6>

                    <!-- Button to open modal -->
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#manageMetasModal">
                        Manage API
                    </button>
                </div>

                <!-- Saved metas displayed here -->
                <div class="mt-2">
                    <ul class="">
                        @forelse($selectedApis as $meta)
                            <li class="list-group-item d-flex justify-content-between align-items-center p-1">
                                <span>{{ $meta->meta_key }}</span>
                                <a href="{{ route('organisations.integration.api.configure', [$organisation->uid, $application->uid, $meta->id])}}" class="btn btn-sm text-primary configure-btn">
                                    <i class="fas fa-fw fa-cog"></i> Configure
                                </a>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">No APIs selected</li>
                        @endforelse
                    </ul>
                </div>
            </div>
            <div class="col-md-6 bg-light p-2">
                <div class="mb-2">
                    <h6 class="text-muted mb-2">Quick Setup</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#setupAccountModal">
                            1. Setup Account
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#clientDetailsModal">
                            2. Client Details
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#authorizeModal">
                            3. Authorize
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#generateTokenModal">
                            4. Generate Token
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#organisationIdModal">
                            5. Organisation ID
                        </button>
                    </div>
                </div>
                <div class="row row-cols-1 row-cols-md-1 g-2">
                    <div class="col">
                        <div class="h-100 p-2">
                            <h6 class="text-muted mb-2">Configuration</h6>
                            <div>
                                <div class="row">
                                    <!-- Left Column -->
                                    <div class="col-md-6">
                                        <div class="mt-1 d-flex justify-content-start gap-2 align-items-center">
                                            <span class="text-muted">Client ID:</span>
                                            @if($integrationStatus['has_client_id'])
                                                <span class="badge bg-success">Configured</span>
                                            @else
                                                <span class="badge bg-danger">Missing</span>
                                            @endif
                                        </div>
                                        <div class="mt-1 d-flex justify-content-start gap-2 align-items-center">
                                            <span class="text-muted">Client Secret:</span>
                                            @if($integrationStatus['has_client_secret'])
                                                <span class="badge bg-success">Configured</span>
                                            @else
                                                <span class="badge bg-danger">Missing</span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Right Column -->
                                    <div class="col-md-6">
                                        <div class="mt-1 d-flex justify-content-start gap-2 align-items-center">
                                            <span class="text-muted">Authorization Code:</span>
                                            @if($integrationStatus['has_auth_code'])
                                                <span class="badge bg-success">Available</span>
                                            @else
                                                <span class="badge bg-danger">Missing</span>
                                            @endif
                                        </div>
                                        <div class="mt-1 d-flex justify-content-start gap-2 align-items-center">
                                            <span class="text-muted">Scopes:</span>
                                            @if($integrationStatus['scopes_configured'])
                                                <span class="badge bg-success">Configured</span>
                                            @else
                                                <span class="badge badge-half-done">Not Configured</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Full width row for scopes list -->
                                @if($integrationStatus['scopes_configured'] && !empty($integrationStatus['scopes_list']))
                                    <div class="mt-1">
                                        <span class="text-muted">Scopes List:</span>
                                        <div>
                                            @foreach($integrationStatus['scopes_list'] as $scope)
                                                <span class="small me-1 mb-1">{{ $scope }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    
                    <div class="col">
                        <div class="h-100 p-2">
                            <h6 class="text-muted mb-2">Authentication</h6>

                            <div class="row">
                                <!-- Row 1 -->
                                <div class="col-md-6">
                                    <div class="mt-1 d-flex justify-content-start gap-2 align-items-center">
                                        <span class="text-muted">Access Token:</span>
                                        @if($integrationStatus['has_access_token'])
                                            @if($integrationStatus['is_access_token_expired'])
                                                <span class="badge bg-warning">Expired</span>
                                            @else
                                                <span class="badge bg-success">Active</span>
                                            @endif
                                        @else
                                            <span class="badge bg-danger">Missing</span>
                                        @endif
                                        @include('integration::components.inc-with-props.entity-buttons', [
                                            'buttons' => [
                                                [
                                                    'modal' => 'confirmAccessTokenGenerateModal',
                                                    'icon' => 'fas fa-fw fa-sync-alt',
                                                    'color' => 'primary',
                                                    'text' => 'Generate',
                                                    'additionalClasses' => 'border-0',
                                                    'permission' => 'view-organisations-integrations'
                                                ]
                                            ]
                                        ])
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    @if($integrationStatus['has_access_token'] && $integrationStatus['token_expires_at'])
                                        <div class="mt-1 d-flex justify-content-start gap-2 align-items-center">
                                            <span class="text-muted">Expires:</span>
                                            <small class="text-muted">
                                                {{ $integrationStatus['token_expires_at_human'] }} ({{ $integrationStatus['token_expires_at'] }})
                                            </small>
                                        </div>
                                    @else
                                        <div class="mt-1 d-flex justify-content-start gap-2 align-items-center">
                                            <span class="text-muted">Expires:</span>
                                            <span class="badge bg-danger">N/A</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Row 2 -->
                                <div class="col-md-6">
                                    <div class="mt-1 d-flex justify-content-start gap-2 align-items-center">
                                        <span class="text-muted">Refresh Token:</span>
                                        @if($integrationStatus['has_refresh_token'])
                                            <span class="badge bg-success">Available</span>
                                        @else
                                            <span class="badge bg-danger">Missing</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mt-1 d-flex justify-content-start gap-2 align-items-center">
                                        <span class="text-muted">Organisation ID:</span>
                                        @if(!empty($integrationStatus['organisation_id']))
                                            <span class="badge bg-success">Configured</span>
                                        @else
                                            <span class="badge bg-danger">Missing</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <hr>
                <div class="d-flex align-items-center justify-content-start gap-2">
                    <h5 class="fs-6 text-muted">Project Entity for Mapping</h5>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#saveEntityModal">Save Entity</button>
                </div>
            </div>
        </div>
    </div>

    @include('integration::components.inc-confirmation-modal', [
        'modalId' => 'confirmAccessTokenGenerateModal',
        'formId' => 'confirmAccessTokenGenerateModal',
        'title' => 'Re-generate access token',
        'action' => route('organisations.integration.zoho-books.regenerate-access-token', [$organisation->uid, $application->uid]),
        'method' => 'POST',
        'message' => 'Once confirmed, a new <strong>access token is generated and saved automatically</strong> for future work.
            Are you sure you want to re-generate it?',
        'submitButtonLabel' => 'Confirm',
        'submitButtonClass' => 'btn-outline-warning',
        'submitButtonDisabled' => !auth()->user()->can('view-organisations-integrations')
    ])

<!-- Manage Metas Modal -->
<div class="modal fade" id="manageMetasModal" tabindex="-1" aria-labelledby="manageMetasModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('organisations.integration.save-api-name', [$organisation->uid, $application->uid]) }}">
                @csrf
                @method('POST')

                <div class="modal-header">
                    <h5 class="modal-title fs-6" id="manageMetasModalLabel">Manage Zoho Books API</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body overflow-auto" style="max-height: 400px;">
                    @php
                        // Get saved IDs from the ZB_api_id meta
                        $savedMeta = $integration ? $integration->metas()->where('meta_key', 'ZB_api_id')->first() : null;
                        $savedIds = $savedMeta ? json_decode($savedMeta->meta_value, true) : [];
                    @endphp

                    <div class="list-group">
                        @foreach($metas as $meta)
                            <label class="d-flex align-items-center list-group-item">
                                <input type="checkbox" class="form-check-input me-2" name="selected_metas[]" value="{{ $meta->id }}"
                                    {{ in_array($meta->id, $savedIds ?? []) ? 'checked' : '' }}>
                                <span class="p-1">{{ $meta->meta_key }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Modal 1: Setup Account -->
    <div class="modal fade" id="setupAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fs-6">Step 1: Setup Zoho Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Follow these steps to create your Zoho developer account:</p>
                    <ol class="ps-3">
                        <li>Go to <a href="https://api-console.zoho.com/" target="_blank" class="text-primary">Zoho API Console</a></li>
                        <li>Sign in with your Zoho account</li>
                        <li>Click <strong>"Add Client"</strong></li>
                        <li>Choose <strong>"Server-based Applications"</strong></li>
                        <li>Fill in these details:
                            <ul class="mt-2">
                                <li><strong>Client Name:</strong> Trackshoot_Integration</li>
                                <li><strong>Homepage URL:</strong> https://trackshoot.com</li>
                                <li><strong>Redirect URI:</strong> https://trackshoot.com/login</li>
                            </ul>
                        </li>
                        <li>Click <strong>"Create"</strong></li>
                    </ol>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Once created, go to the "Client Secret" tab to find your credentials.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Close</button>
                    <a href="https://api-console.zoho.com/" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt me-2"></i>Open Zoho Console
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 2: Client Details -->
    <div class="modal fade" id="clientDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('organisations.integration.zoho-books.store', [$organisation->uid, $application->uid]) }}" method="POST">
                    @csrf
                    <input type="hidden" name="form_type" value="client_details">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6">Step 2: Client Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Copy your Client ID and Secret from Zoho API Console:</p>
                        <div class="mb-3">
                            <label class="form-label">Client ID</label>
                            <input type="text" class="form-control" name="client_id" 
                                value="{{ old('client_id', $clientId ?? '') }}" 
                                placeholder="Enter your Client ID" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Client Secret</label>
                            <input type="text" class="form-control" name="client_secret" 
                                value="{{ old('client_secret', $clientSecret ?? '') }}" 
                                placeholder="Enter your Client Secret" required>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Find these in the "Client Secret" tab of your Zoho application.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Save Details</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 3: Authorize -->
    <div class="modal fade" id="authorizeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fs-6">Step 3: Authorize Integration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Complete the authorization process:</p>
                    
                    <div class="mb-3">
                        <label class="form-label">1. Copy this authorization URL:</label>
                        <div class="d-flex align-items-center">
                            @php
                                $authUrl = '';
                                if(isset($clientId) && !empty($clientId) && !empty($selectedScopes)) {
                                    $authUrl = 'https://accounts.zoho.in/oauth/v2/auth?scope=' . implode(',', $selectedScopes)
                                            . '&client_id=' . $clientId
                                            . '&response_type=code&redirect_uri=' . urlencode('https://trackshoot.com/login')
                                            . '&access_type=offline';
                                } else {
                                    $authUrl = 'Please complete Client Details and Select Scope first';
                                }
                            @endphp

                            <input type="text" class="form-control" id="authUrlField" readonly value="{{ $authUrl }}">
                            @if(isset($clientId) && isset($selectedScopes) && $clientId && !empty($selectedScopes))
                            <button type="button" class="btn btn-outline-secondary ms-2 copy-btn" 
                                    data-copy-target="#authUrlField">
                                <i class="fas fa-copy"></i>
                            </button>
                            @endif
                        </div>
                    </div>

                    <div class="mb-3">
                        <p>2. Open the URL in a new tab and accept the permissions</p>
                        <p>3. After accepting, copy the redirected URL and paste it below:</p>
                    </div>

                    <form action="{{ route('organisations.integration.zoho-books.store', [$organisation->uid, $application->uid]) }}" method="POST">
                        @csrf
                        <input type="hidden" name="form_type" value="save_url">
                        <div class="mb-3">
                            <label class="form-label">Redirected URL</label>
                            <input type="text" class="form-control" name="copid_url_code" 
                                value="{{ old('copid_url_code', $code ?? '') }}" 
                                placeholder="Paste the full URL after authorization" required>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Save URL</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 4: Generate Token -->
    <div class="modal fade" id="generateTokenModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fs-6">Step 4: Generate Access Token</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Generate your access token to complete the integration:</p>
                    
                    @if(isset($clientId) && isset($clientSecret) && isset($code) && $clientId && $clientSecret && $code)
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            All required information is available. You can generate the token now.
                        </div>
                        
                        <form action="{{ route('organisations.integration.zoho-books.tokens', [$organisation->uid, $application->uid]) }}" method="POST">
                            @csrf
                            <input type="hidden" name="organisation_uid" value="{{ $organisation->uid }}">
                            <div class="modal-footer">
                                <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-key me-2"></i>Generate Token
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Please complete the previous steps before generating tokens.
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Close</button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 5: Organisation ID -->
    <div class="modal fade" id="organisationIdModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('organisations.integration.zoho-books.store', [$organisation->uid, $application->uid]) }}" method="POST">
                    @csrf
                    <input type="hidden" name="form_type" value="organisation_id">
                    <div class="modal-header">
                        <h5 class="modal-title fs-6">Step 5: Organisation ID</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Find your Zoho Books Organisation ID:</p>
                        <ol class="ps-3 mb-3">
                            <li>Go to your Zoho Books dashboard</li>
                            <li>Click on your profile button</li>
                            <li>In the right sidebar, find "Organization ID"</li>
                            <li>Copy and paste it below</li>
                        </ol>
                        
                        <div class="mb-3">
                            <label class="form-label">Organisation ID</label>
                            <input type="text" class="form-control" name="organisation_id" 
                                value="{{ old('organisation_id', $organisationId ?? '') }}" 
                                placeholder="Enter your Zoho Books Organisation ID" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Save ID</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<!-- Save Entity Modal -->
<div class="modal fade" id="saveEntityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('organisations.integration.save-entity-configuration', [$organisation->uid, $application->uid]) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fs-6">Save Project Entity for Mapping with APIs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Write the entity names separated by commas.  
                        Example: <code>persons, projects, tasks</code>.  
                        We search for unique columns and unique meta keys, and we prefer a table structure like <code>persons, person_metas</code>.
                    </p>

                    <!-- Entity names input -->
                    <div class="mb-3">
                        <label for="entityNames" class="form-label">Entity Names (comma separated)</label>
                        <input type="text" class="form-control" id="entityNames" name="entity_names" 
                               value="{{ old('entity_names', $existingEntityConfig['entity_names'] ?? '') }}" 
                               placeholder="e.g. persons, projects, tasks" required>
                    </div>

                    <!-- Default entity field -->
                    <div class="mb-3">
                        <label for="defaultEntity" class="form-label">Default Entity</label>
                        <input type="text" class="form-control" id="defaultEntity" name="default_entity" 
                               value="{{ old('default_entity', $existingEntityConfig['default_entity'] ?? '') }}" 
                               placeholder="e.g. persons" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced copy functionality with modern Clipboard API
    document.addEventListener('click', function(e) {
        if (e.target.closest('.copy-btn')) {
            const button = e.target.closest('.copy-btn');
            let textToCopy;
            
            // Check if we should copy from a target element
            if (button.hasAttribute('data-copy-target')) {
                const target = document.querySelector(button.getAttribute('data-copy-target'));
                textToCopy = target ? target.value || target.textContent.trim() : '';
            } 
            // Otherwise use direct text
            else if (button.hasAttribute('data-copy-text')) {
                textToCopy = button.getAttribute('data-copy-text');
            }
            
            if (!textToCopy) {
                console.warn('No text to copy found');
                return;
            }
            
            // Function to show visual feedback
            const showFeedback = (success = true) => {
                const originalIcon = button.querySelector('i');
                if (originalIcon) {
                    const originalClass = originalIcon.className;
                    const originalButtonClass = Array.from(button.classList);
                    
                    if (success) {
                        // Change icon to checkmark
                        originalIcon.className = 'fas fa-check';
                        button.classList.remove('btn-outline-secondary');
                        button.classList.add('btn-outline-success');
                    } else {
                        // Change icon to error
                        originalIcon.className = 'fas fa-times';
                        button.classList.remove('btn-outline-secondary');
                        button.classList.add('btn-outline-danger');
                    }
                    
                    // Reset after 2 seconds
                    setTimeout(() => {
                        originalIcon.className = originalClass;
                        button.className = originalButtonClass.join(' ');
                    }, 2000);
                }
            };
            
            // Try modern Clipboard API first
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(textToCopy)
                    .then(() => {
                        console.log('Text copied successfully using Clipboard API');
                        showFeedback(true);
                    })
                    .catch(err => {
                        console.error('Clipboard API failed, trying fallback: ', err);
                        fallbackCopy(textToCopy, showFeedback);
                    });
            } else {
                // Fallback for older browsers or non-secure contexts
                console.log('Clipboard API not available, using fallback');
                fallbackCopy(textToCopy, showFeedback);
            }
        }
    });
    
    // Fallback copy method
    function fallbackCopy(text, feedbackCallback) {
        try {
            // Create temporary textarea element
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            textarea.style.pointerEvents = 'none';
            
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            
            // Try to copy
            const successful = document.execCommand('copy');
            
            if (successful) {
                console.log('Text copied successfully using fallback method');
                feedbackCallback(true);
            } else {
                console.error('Fallback copy failed');
                feedbackCallback(false);
                // Show user-friendly error
                alert('Failed to copy to clipboard. Please copy the text manually.');
            }
            
        } catch (err) {
            console.error('Fallback copy error: ', err);
            feedbackCallback(false);
            // Show user-friendly error
            alert('Failed to copy to clipboard. Please copy the text manually.');
        } finally {
            // Remove temporary textarea
            const textarea = document.querySelector('textarea[style*="opacity: 0"]');
            if (textarea) {
                document.body.removeChild(textarea);
            }
        }
    }
});       
</script>
@endpush