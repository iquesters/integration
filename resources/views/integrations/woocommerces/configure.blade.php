@extends(app('app.layout'))

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make(['Configure', ($integration->name ?? 'Integration'), 'Woocommerce', 'Integration']))
@section('meta-description', \Iquesters\Foundation\Helpers\MetaHelper::description('Configure page of Integration'))

@php
    $tabs = [
        [
            'route' => 'integration.show',
            'params' => [
                'integrationUid' => $integration->uid,
            ],
            'icon' => 'far fa-fw fa-list-alt',
            'label' => 'Overview',
            // 'permission' => 'view-organisations',
        ],
        [
            'route' => 'integration.configure',
            'params' => [
                'integrationUid' => $integration->uid,
            ],
            'icon' => 'fas fa-fw fa-sliders-h',
            'label' => 'Configure',
            // 'permission' => 'view-organisations-users',
        ],
        [
            'route' => 'integration.apiconf',
            'params' => [
                'integrationUid' => $integration->uid,
            ],
            'icon' => 'fas fa-fw fa-screwdriver-wrench',
            'label' => 'Api Conf',
            // 'permission' => 'view-teams'
        ],
        [
            'route' => 'integration.syncdata',
            'params' => [
                'integrationUid' => $integration->uid,
            ],
            'icon' => 'fas fa-fw fa-rotate',
            'label' => 'Sync Data',
            // 'permission' => 'view-teams'
        ]
    ];
@endphp

@section('content')
<div>
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-start gap-2 mb-3">
        <h5 class="mb-0 text-muted">
            {{ $integration->name }}
            {!! $integration->supportedInt?->getMeta('icon') !!}
        </h5>
        <span class="badge badge-{{ strtolower($integration->status) }}">
            {{ ucfirst($integration->status) }}
        </span>
    </div>

    {{-- Main Configuration Row --}}
    <div class="row g-2">
        {{-- Left Column: Website URL & Preview --}}
        <div class="col-lg-6">
            {{-- Configuration Steps --}}
            <div class="border rounded p-2">
                <h6 class="mb-2 text-secondary">Configuration Steps</h6>

                <ul class="list-unstyled mb-0" id="configSteps">

                    <li class="mb-2">
                        Enter main domain URL
                        <ul class="text-muted small mt-1 list-unstyled" id="step1-messages"></ul>
                    </li>

                    <li class="mb-2">
                        Click "Check" to verify website
                        <ul class="text-muted small mt-1 list-unstyled" id="step2-messages"></ul>
                    </li>

                    <li class="mb-2">
                        Enter Consumer Key (CK)
                        <ul class="text-muted small mt-1 list-unstyled" id="step3-messages"></ul>
                    </li>

                    <li class="mb-2">
                        Enter Consumer Secret (CS)
                        <ul class="text-muted small mt-1 list-unstyled" id="step4-messages"></ul>
                    </li>

                    <li class="mb-2">
                        Test API connection
                        <ul class="text-muted small mt-1 list-unstyled" id="step5-messages"></ul>
                    </li>

                    <li class="mb-0">
                        Save configuration
                        <ul class="text-muted small mt-1 list-unstyled" id="step6-messages"></ul>
                    </li>

                </ul>

                <hr class="my-3">

                <p class="text-muted mb-0">
                    Need help getting API keys?
                    <a href="#woocommerce-docs" class="text-decoration-none">View documentation below</a>
                </p>
            </div>
        </div>

        {{-- Right Column: API Keys & Steps --}}
        <div class="col-lg-6">

            <div class="mb-2">
                <label for="website_url" class="form-label">
                    Website URL <span class="text-muted">(Main domain only)</span>
                </label>

                <div class="input-group">
                    <input
                        type="url"
                        id="website_url"
                        class="form-control"
                        placeholder="https://example.com"
                        value="{{ $websiteUrl ?? '' }}"
                    >

                    <button class="btn btn-outline-primary" id="checkBtn" type="button" disabled>
                        <span id="btnText">Check</span>
                        <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2" style="display:none;"></span>
                    </button>
                </div>
            </div>

            {{-- Preview Section --}}
            <div class="border rounded p-2 mb-2">

                {{-- Default State --}}
                <div id="noPreview" style="display:{{ empty($websiteUrl) ? 'block' : 'none' }};">
                    <div class="d-flex align-items-start gap-2">
                        <div class="preview-box flex-shrink-0" style="width: 80px; height: 80px; border: 1px dashed #dee2e6; border-radius: 8px; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-globe text-muted" style="font-size: 32px;"></i>
                        </div>

                        <div class="flex-grow-1" style="min-width: 0;">
                            <h6 class="mb-2 text-muted">No website preview available</h6>
                            <p class="text-muted mb-2" style="line-height: 1.5;">
                                Click <strong>“Check”</strong> to verify website
                            </p>
                            <span class="text-muted small">
                                https://example.com
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Preview Content --}}
                <div id="previewContent" style="display:{{ !empty($websiteUrl) ? 'block' : 'none' }};">
                    <div class="d-flex align-items-start gap-2">
                        <div class="preview-box flex-shrink-0" style="width: 80px; height: 80px; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; background: #fff; display: flex; align-items: center; justify-content: center; position: relative;">
                            <img id="previewImage" src="" alt="Preview" style="display: none; width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0;">
                            <img id="websiteFavicon" src="" alt="Favicon" style="width: 32px; height: 32px; display: none; position: relative; z-index: 1;">
                            <div id="placeholderIcon" style="display: flex; align-items: center; justify-content: center; color: #adb5bd; font-size: 32px;">
                                <i class="fa-solid fa-globe"></i>
                            </div>
                        </div>
                        
                        <div class="flex-grow-1" style="min-width: 0;">
                            <h6 id="websiteTitle" class="mb-2 text-truncate">{{ !empty($websiteUrl) ? 'Website Verified' : '' }}</h6>
                            <p id="websiteDescription" class="text-muted mb-2" style="line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ !empty($websiteUrl) ? 'Configuration saved' : '' }}</p>
                            <a id="websiteLink" href="{{ $websiteUrl ?? '' }}" target="_blank" class="text-decoration-none d-inline-flex align-items-center gap-1">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                <span id="websiteLinkText" class="text-truncate" style="max-width: 300px;">{{ $websiteUrl ?? '' }}</span>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
            <div class="row g-2 mb-2 mt-3">
                <div class="col-12">
                    <label for="consumer_key" class="form-label">Consumer Key</label>
                    <input 
                        type="text" 
                        id="consumer_key" 
                        class="form-control" 
                        placeholder="ck_xxxxxxxxxx"
                        value="{{ $consumerKey ?? '' }}"
                    >
                </div>
                <div class="col-12">
                    <label for="consumer_secret" class="form-label">Consumer Secret</label>
                    <input 
                        type="text" 
                        id="consumer_secret" 
                        class="form-control" 
                        placeholder="cs_xxxxxxxxxx"
                        value="{{ $consumerSecret ?? '' }}"
                    >
                </div>
            </div>

            <div class="d-flex gap-2 mb-2">
                <button class="btn btn-sm btn-outline-info" id="testApiBtn" disabled>
                    <span id="testBtnText">Test Connection</span>
                    <span id="testBtnSpinner" class="spinner-border spinner-border-sm ms-2" style="display:none;"></span>
                </button>
                <button class="btn btn-sm btn-outline-primary" id="saveBtn" style="display:none;">
                    <span>Save Configuration</span>
                </button>
            </div>

        
        </div>
    </div>

    {{-- Success Message --}}
    @if(!empty($isActive))
    <div class="row mt-4">
        <div class="col-12">
            <div class="alert alert-success text-center mb-0">
                <i class="fa-solid fa-circle-check me-2"></i> Configuration Successfully Saved
            </div>
        </div>
    </div>
    @endif

    {{-- Website URL Documentation --}}
    <div class="mt-5 pt-4 border-top">
        <div class="mb-4">
            <h5 class="fs-6 mb-3">
                Website URL Requirements
            </h5>
            <p class="mb-2">
                <strong>Important:</strong> Please provide only your main domain URL, not specific page URLs.
            </p>
            <ul class="mb-0">
                <li><strong>Correct:</strong> <code class="text-success">https://example.com</code> or <code class="text-success">https://www.example.com</code></li>
                <li><strong>Incorrect:</strong> <code class="text-danger">https://example.com/shop</code> or <code class="text-danger">https://example.com/wp-admin</code></li>
            </ul>
        </div>
    </div>

    {{-- WooCommerce API Documentation --}}
    <div class="mt-4 pt-4 border-top" id="woocommerce-docs">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="fs-6 mb-0">
                WooCommerce API Configuration Guide
            </h5>
        </div>

        <div class="text-muted">
            <p class="mb-3">
                To create or manage keys for a specific WordPress user, go to
                <strong>WooCommerce > Settings > Advanced > REST API</strong>.
            </p>

            <p class="mb-4">
                <em>Note: Keys/Apps was found at WooCommerce > Settings > API > Key/Apps
                prior to WooCommerce 3.4.</em>
            </p>

            <div class="mb-4">
                <img
                    src="{{ \Iquesters\Integration\IntegrationServiceProvider::asset('img/woocommerce/api-keys-settings.png') }}"
                    class="img-fluid rounded border"
                    alt="api-keys-settings"
                    style="max-width: 700px;">
            </div>

            <p class="mb-3">
                Click the <strong>"Add Key"</strong> button. In the next screen, add a description and
                select the WordPress user you would like to generate the key for.
                Use of the REST API with the generated keys will conform to that user's
                WordPress roles and capabilities.
            </p>

            <p class="mb-4">
                Choose the level of access for this REST API key, which can be <strong>Read access</strong>,
                <strong>Write access</strong> or <strong>Read/Write access</strong>. Then click the 
                <strong>"Generate API Key"</strong> button and WooCommerce will generate REST API keys 
                for the selected user.
            </p>

            <div class="mb-4">
                <img
                    src="{{ \Iquesters\Integration\IntegrationServiceProvider::asset('img/woocommerce/creating-api-keys.png') }}"
                    class="img-fluid rounded border"
                    alt="creating-api-keys"
                    style="max-width: 700px;">
            </div>

            <p class="mb-3">
                Now that keys have been generated, you should see two new keys, a QRCode,
                and a Revoke API Key button. These two keys are your <strong>Consumer Key</strong> and
                <strong>Consumer Secret</strong>.
            </p>

            <div class="mb-4">
                <img
                    src="{{ \Iquesters\Integration\IntegrationServiceProvider::asset('img/woocommerce/api-key-generated.png') }}"
                    class="img-fluid rounded border"
                    alt="api-key-generated"
                    style="max-width: 700px;">
            </div>
            <p>
                <small>
                    For full details, refer to the official WooCommerce documentation.
                    <a href="https://woocommerce.github.io/woocommerce-rest-api-docs/#authentication" target="_blank" class="text-decoration-none ms-1">
                        <i class="fas fa-fw fa-external-link-alt"></i>
                        Official Documentation
                    </a>
                </small>
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Helper function to add step messages
function addStepMessage(stepId, message, type = 'info', messageId = null) {
    const container = document.getElementById(`${stepId}-messages`);
    if (!container) return;

    const li = document.createElement('li');
    if (messageId) {
        li.id = messageId;
    }

    const icons = {
        info: 'fa-circle-info',
        success: 'fa-circle-check text-success',
        warning: 'fa-triangle-exclamation text-warning',
        error: 'fa-circle-xmark text-danger',
        loading: 'fa-spinner fa-spin'
    };

    li.innerHTML = `<i class="fa-solid ${icons[type]} me-1"></i>${message}`;
    container.appendChild(li);
}

// Helper function to remove a specific message
function removeStepMessage(messageId) {
    const message = document.getElementById(messageId);
    if (message) {
        message.remove();
    }
}

// Step management
function updateStep(stepId, checked) {
    const checkbox = document.getElementById(stepId);
    if (checkbox) {
        checkbox.checked = checked;
    }
}

// Check if website URL is filled
const websiteInput = document.getElementById('website_url');
const checkBtn = document.getElementById('checkBtn');

websiteInput.addEventListener('input', function () {
    const hasValue = this.value.trim().length > 0;
    const container = document.getElementById('step1-messages');
    
    // Clear previous messages
    container.innerHTML = '';
    
    if (hasValue) {
        addStepMessage('step1', `URL entered: ${this.value.trim()}`, 'success');
        checkBtn.disabled = false;
    } else {
        checkBtn.disabled = true;
    }
    
    updateStep('step1', hasValue);
});

// Enable test button when both keys are filled
function checkApiFields() {
    const key = document.getElementById('consumer_key').value.trim();
    const secret = document.getElementById('consumer_secret').value.trim();
    const testApiBtn = document.getElementById('testApiBtn');
    
    // Clear and update step 3 messages
    const step3Container = document.getElementById('step3-messages');
    step3Container.innerHTML = '';
    if (key.length > 0) {
        addStepMessage('step3', `Consumer Key: ${key}`, 'success');
    }
    updateStep('step3', key.length > 0);
    
    // Clear and update step 4 messages
    const step4Container = document.getElementById('step4-messages');
    step4Container.innerHTML = '';
    if (secret.length > 0) {
        // Mask the secret for security (show first 4 and last 4 characters)
        const masked = secret.length > 8 
            ? `${secret.substring(0, 4)}...${secret.substring(secret.length - 4)}`
            : '••••••••';
        addStepMessage('step4', `Consumer Secret: ${masked}`, 'success');
    }
    updateStep('step4', secret.length > 0);
    
    testApiBtn.disabled = !(key && secret);
}

document.getElementById('consumer_key').addEventListener('input', checkApiFields);
document.getElementById('consumer_secret').addEventListener('input', checkApiFields);

// Website Check
document.getElementById('checkBtn').addEventListener('click', async function () {
    const url = document.getElementById('website_url').value;
    const noPreview = document.getElementById('noPreview');
    const previewContent = document.getElementById('previewContent');
    const statusMessage = document.getElementById('statusMessage');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');
    const checkBtn = document.getElementById('checkBtn');

    // Reset
    if (statusMessage) {
        statusMessage.style.display = 'none';
        statusMessage.className = 'py-2 mb-3';
        statusMessage.innerHTML = '';
    }

    if (!url) {
        alert('Please enter a website URL');
        return;
    }

    try {
        new URL(url);
    } catch (e) {
        alert('Please enter a valid URL (must include http:// or https://)');
        return;
    }

    // Show loading state
    checkBtn.disabled = true;
    btnSpinner.style.display = 'inline-block';
    
    // Add loading message with unique ID
    addStepMessage('step2', 'Verifying website...', 'loading', 'step2-verifying');

    try {
        const response = await fetch('/api/fetch-website', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ url: url })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            // Reset preview box
            document.getElementById('previewImage').style.display = 'none';
            document.getElementById('websiteFavicon').style.display = 'none';
            document.getElementById('placeholderIcon').style.display = 'flex';

            // Display preview data
            document.getElementById('websiteTitle').innerHTML = data.title || 'No title found';
            document.getElementById('websiteDescription').innerHTML = data.description || 'No description available';
            document.getElementById('websiteLink').href = url;
            document.getElementById('websiteLinkText').innerHTML = url;

            if (data.image) {
                document.getElementById('previewImage').src = data.image;
                document.getElementById('previewImage').style.display = 'block';
                document.getElementById('placeholderIcon').style.display = 'none';
            } else if (data.favicon) {
                document.getElementById('websiteFavicon').src = data.favicon;
                document.getElementById('websiteFavicon').style.display = 'block';
                document.getElementById('placeholderIcon').style.display = 'none';
            }

            noPreview.style.display = 'none';
            previewContent.style.display = 'block';
            
            // Add success message (keep verifying message)
            if (response.status === 200) {
                addStepMessage('step2', 'Website verified successfully', 'success');
                if (statusMessage) {
                    statusMessage.innerHTML = '<i class="fa-solid fa-circle-check me-2"></i>Website verified successfully!';
                    statusMessage.classList.add('text-success');
                    statusMessage.style.display = 'block';
                }
                updateStep('step2', true);
            } else {
                addStepMessage('step2', 'Website found but verification incomplete', 'warning');
                if (statusMessage) {
                    statusMessage.innerHTML = '<i class="fa-solid fa-exclamation-triangle me-2"></i>Website found but verification incomplete.';
                    statusMessage.classList.add('text-warning');
                    statusMessage.style.display = 'block';
                }
            }
        } else {
            // Remove verifying message and add error
            removeStepMessage('step2-verifying');
            addStepMessage('step2', 'Verification failed', 'error');
            if (statusMessage) {
                statusMessage.innerHTML = `<i class="fa-solid fa-circle-xmark me-2"></i>${data.message || 'Unable to verify website'}`;
                statusMessage.classList.add('text-danger');
                statusMessage.style.display = 'block';
            }
            noPreview.style.display = 'none';
            previewContent.style.display = 'block';
        }

    } catch (error) {
        // Remove verifying message and add error
        removeStepMessage('step2-verifying');
        addStepMessage('step2', 'Network error occurred', 'error');
        if (statusMessage) {
            statusMessage.innerHTML = '<i class="fa-solid fa-circle-xmark me-2"></i>Network error. Please try again.';
            statusMessage.classList.add('text-danger');
            statusMessage.style.display = 'block';
        }
    } finally {
        checkBtn.disabled = false;
        btnSpinner.style.display = 'none';
    }
});

// Test API Connection
document.getElementById('testApiBtn').addEventListener('click', async function () {
    const url = document.getElementById('website_url').value;
    const consumerKey = document.getElementById('consumer_key').value.trim();
    const consumerSecret = document.getElementById('consumer_secret').value.trim();
    const testBtnText = document.getElementById('testBtnText');
    const testBtnSpinner = document.getElementById('testBtnSpinner');
    const testApiBtn = document.getElementById('testApiBtn');
    const saveBtn = document.getElementById('saveBtn');
    const statusMessage = document.getElementById('statusMessage');

    testApiBtn.disabled = true;
    testBtnSpinner.style.display = 'inline-block';
    
    // Add loading message with unique ID
    addStepMessage('step5', 'Verifying API connection...', 'loading', 'step5-verifying');

    try {
        const baseUrl = url.replace(/\/$/, '');
        const testEndpoint = `${baseUrl}/wp-json/wc/v3/system_status`;
        const auth = btoa(`${consumerKey}:${consumerSecret}`);
        
        const response = await fetch(testEndpoint, {
            method: 'GET',
            headers: {
                'Authorization': `Basic ${auth}`,
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();
        
        if (response.ok) {
            // Add success message (keep verifying message)
            addStepMessage('step5', 'API verified successfully', 'success');
            
            if (statusMessage) {
                statusMessage.innerHTML = '<i class="fa-solid fa-circle-check me-2"></i>API connection successful! You can now save the configuration.';
                statusMessage.classList.remove('alert-danger', 'alert-warning');
                statusMessage.classList.add('alert-success');
                statusMessage.style.display = 'block';
            }
            
            saveBtn.style.display = 'inline-block';
            updateStep('step5', true);
        } else {
            // Remove verifying message and add error
            removeStepMessage('step5-verifying');
            addStepMessage('step5', 'API verification failed', 'error');
            
            if (statusMessage) {
                statusMessage.innerHTML = '<i class="fa-solid fa-circle-xmark me-2"></i>API connection failed. Please check your credentials.';
                statusMessage.classList.remove('alert-success', 'alert-warning');
                statusMessage.classList.add('alert-danger');
                statusMessage.style.display = 'block';
            }
        }

    } catch (error) {
        // Remove verifying message and add error
        removeStepMessage('step5-verifying');
        addStepMessage('step5', 'Connection error occurred', 'error');
        
        if (statusMessage) {
            statusMessage.innerHTML = '<i class="fa-solid fa-circle-xmark me-2"></i>Connection error. Please verify your credentials and try again.';
            statusMessage.classList.remove('alert-success', 'alert-warning');
            statusMessage.classList.add('alert-danger');
            statusMessage.style.display = 'block';
        }
    } finally {
        testApiBtn.disabled = false;
        testBtnSpinner.style.display = 'none';
    }
});

// Save Configuration
document.getElementById('saveBtn').addEventListener('click', async function () {
    const url = document.getElementById('website_url').value;
    const consumerKey = document.getElementById('consumer_key').value.trim();
    const consumerSecret = document.getElementById('consumer_secret').value.trim();
    const saveBtn = document.getElementById('saveBtn');

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    
    // Add loading message with unique ID
    addStepMessage('step6', 'Saving configuration...', 'loading', 'step6-saving');

    try {
        const response = await fetch('/integrations/save-configuration', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                url: url,
                consumer_key: consumerKey,
                consumer_secret: consumerSecret
            })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            // Add success message (keep saving message)
            addStepMessage('step6', 'Configuration saved successfully', 'success');
            updateStep('step6', true);
            
            if (data.redirect) {
                window.location.href = data.redirect;
            }
        } else {
            // Remove saving message and add error
            removeStepMessage('step6-saving');
            addStepMessage('step6', 'Failed to save configuration', 'error');
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<span>Save Configuration</span>';
        }

    } catch (error) {
        // Remove saving message and add error
        removeStepMessage('step6-saving');
        addStepMessage('step6', 'Error saving configuration', 'error');
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span>Save Configuration</span>';
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const websiteUrl = document.getElementById('website_url').value.trim();
    const consumerKey = document.getElementById('consumer_key').value.trim();
    const consumerSecret = document.getElementById('consumer_secret').value.trim();
    
    const checkBtn = document.getElementById('checkBtn');
    checkBtn.disabled = websiteUrl.length === 0;

    // Initialize step 1
    if (websiteUrl.length > 0) {
        addStepMessage('step1', `URL entered: ${websiteUrl}`, 'success');
    }
    updateStep('step1', websiteUrl.length > 0);
    
    // Initialize step 2
    updateStep('step2', {{ !empty($websiteUrl) ? 'true' : 'false' }});
    @if(!empty($websiteUrl))
        addStepMessage('step2', 'Website verified successfully', 'success');
    @endif
    
    // Initialize step 3
    if (consumerKey.length > 0) {
        addStepMessage('step3', `Consumer Key: ${consumerKey}`, 'success');
    }
    updateStep('step3', consumerKey.length > 0);
    
    // Initialize step 4
    if (consumerSecret.length > 0) {
        const masked = consumerSecret.length > 8 
            ? `${consumerSecret.substring(0, 4)}...${consumerSecret.substring(consumerSecret.length - 4)}`
            : '••••••••';
        addStepMessage('step4', `Consumer Secret: ${masked}`, 'success');
    }
    updateStep('step4', consumerSecret.length > 0);
    
    // Initialize step 5
    updateStep('step5', {{ !empty($isActive) ? 'true' : 'false' }});
    @if(!empty($isActive))
        addStepMessage('step5', 'API verified successfully', 'success');
    @endif
    
    // Initialize step 6
    updateStep('step6', {{ !empty($isActive) ? 'true' : 'false' }});
    @if(!empty($isActive))
        addStepMessage('step6', 'Configuration saved successfully', 'success');
    @endif
    
    checkApiFields();
});
</script>
@endpush