@extends(app('app.layout'))

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make([($integration->name ?? 'Integration'), 'Woocommerce', 'Integration']))
@section('meta-description', \Iquesters\Foundation\Helpers\MetaHelper::description('Configure page of Integration'))

@section('content')
<div>
    <div class="d-flex align-items-center justify-content-start gap-2 mb-2">
        <h5 class="fs-6 text-muted mb-0">
            {{ $integration->name }}
            {!! $integration->supportedInt?->getMeta('icon') !!}
        </h5>
        <span class="badge badge-{{ strtolower($integration->status) }}">
            {{ ucfirst($integration->status) }}
        </span>
    </div>

    <div class="d-flex align-items-end w-75 gap-2">
        <div class="form-group flex-grow-1">
            <label for="website_url mb-2">Website URL</label>
            <input
                type="url"
                id="website_url"
                class="form-control"
                placeholder="https://example.com"
                value="{{ $websiteUrl ?? '' }}"
                {{ !empty($websiteUrl) ? '' : '' }}
            >
        </div>

        <button class="btn btn-sm btn-outline-primary" id="checkBtn" {{ !empty($websiteUrl) ? '' : '' }}>
            <span id="btnText">Check</span>
            <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2" style="display:none;"></span>
        </button>
    </div>

    {{-- Preview and Response Section --}}
    <div id="previewSection" class="mt-4" style="display:{{ !empty($websiteUrl) ? 'block' : 'none' }};">
        <h5 class="fs-6 text-muted mb-3">Website Preview</h5>
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body p-2">
                        <div class="d-flex align-items-start gap-2">
                            {{-- Preview Box (150x150) --}}
                            <div class="preview-box flex-shrink-0" style="width: 150px; height: 150px; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; background: #f8f9fa; display: flex; align-items: center; justify-content: center; position: relative;">
                                <img id="previewImage" src="" alt="Preview" style="display: none; width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0;">
                                <img id="websiteFavicon" src="" alt="Favicon" style="width: 48px; height: 48px; display: none; position: relative; z-index: 1;">
                                <div id="placeholderIcon" style="display: flex; align-items: center; justify-content: center; color: #adb5bd; font-size: 48px;">
                                    <i class="fa-solid fa-globe fs-1"></i>
                                </div>
                            </div>
                            
                            {{-- Website Info --}}
                            <div class="flex-grow-1" style="min-width: 0;">
                                <h6 id="websiteTitle" class="mb-2 text-truncate" style="font-size: 0.95rem; font-weight: 600;">{{ !empty($websiteUrl) ? 'Website Verified' : '' }}</h6>
                                <p id="websiteDescription" class="text-muted mb-2 small" style="font-size: 0.8rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">{{ !empty($websiteUrl) ? 'Configuration saved' : '' }}</p>
                                <a id="websiteLink" href="{{ $websiteUrl ?? '' }}" target="_blank" class="text-decoration-none small d-inline-flex align-items-center gap-1" style="font-size: 0.75rem; color: #0d6efd;">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    <span id="websiteLinkText" class="text-truncate" style="max-width: 150px;">{{ $websiteUrl ?? '' }}</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Response Display --}}
            <div class="col-md-6">
                <div class="card shadow-sm" style="height: 100%;">
                    <div class="card-body p-2 d-flex flex-column align-items-center justify-content-center">
                        <h6 class="mb-3" style="font-size: 0.9rem; font-weight: 600;">Status</h6>
                        <div id="websiteStatus" class="d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; border-radius: 50%; font-size: 2rem; font-weight: bold; background-color: {{ !empty($websiteUrl) ? '#d4edda' : '' }}; color: {{ !empty($websiteUrl) ? '#155724' : '' }}; border: {{ !empty($websiteUrl) ? '3px solid #c3e6cb' : '' }};">{{ !empty($websiteUrl) ? '200' : '' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Status Message --}}
    <div id="statusMessage" class="mt-4 {{ !empty($websiteUrl) ? 'fw-bold text-success' : '' }}">
        {{ !empty($websiteUrl) ? 'Website verified successfully! Please configure WooCommerce API keys below.' : '' }}
    </div>

    {{-- WooCommerce API Configuration --}}
    <div id="apiConfigSection" class="mt-4" style="display:{{ !empty($websiteUrl) ? 'block' : 'none' }};">
        <div>
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fs-6 mb-0">WooCommerce API Configuration</h5>
                <a href="https://woocommerce.github.io/woocommerce-rest-api-docs/#authentication" target="_blank" class="btn btn-sm btn-outline-info d-inline-flex align-items-center gap-1">
                    <i class="fas fa-fe fa-circle-info"></i>
                    Documentation
                </a>
            </div>

            <div class="mb-3 text-muted">
                <p>
                    To create or manage keys for a specific WordPress user, go to
                    WooCommerce > Settings > Advanced > REST API.
                </p>

                <p class="small">
                    Note: Keys/Apps was found at WooCommerce > Settings > API > Key/Apps
                    prior to WooCommerce 3.4.
                </p>

                {{-- Image 1 --}}
                <div class="row my-3">
                    <div class="col-12 col-lg-6">
                        <img
                            src="{{ \Iquesters\Integration\IntegrationServiceProvider::asset('img/woocommerce/api-keys-settings.png') }}"
                            class="img-fluid"
                            alt="api-keys-settings">
                    </div>
                </div>

                <p>
                    Click the "Add Key" button. In the next screen, add a description and
                    select the WordPress user you would like to generate the key for.
                    Use of the REST API with the generated keys will conform to that user's
                    WordPress roles and capabilities.
                </p>

                <p>
                    Choose the level of access for this REST API key, which can be Read access,
                    Write access or Read/Write access. Then click the "Generate API Key"
                    button and WooCommerce will generate REST API keys for the selected user.
                </p>

                {{-- Image 2 --}}
                <div class="row my-3">
                    <div class="col-12 col-lg-6">
                        <img
                            src="{{ \Iquesters\Integration\IntegrationServiceProvider::asset('img/woocommerce/creating-api-keys.png') }}"
                            class="img-fluid"
                            alt="creating-api-keys">
                    </div>
                </div>

                <p>
                    Now that keys have been generated, you should see two new keys, a QRCode,
                    and a Revoke API Key button. These two keys are your Consumer Key and
                    Consumer Secret.
                </p>

                {{-- Image 3 --}}
                <div class="row my-3">
                    <div class="col-12 col-lg-6">
                        <img
                            src="{{ \Iquesters\Integration\IntegrationServiceProvider::asset('img/woocommerce/api-key-generated.png') }}"
                            class="img-fluid"
                            alt="api-key-generated">
                    </div>
                </div>

            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="consumer_key" class="form-label">Consumer Key</label>
                        <input 
                            type="text" 
                            id="consumer_key" 
                            class="form-control" 
                            placeholder="ck_xxxxxxxxxx"
                            value="{{ $consumerKey ?? '' }}"
                            {{ !empty($consumerKey) ? '' : '' }}
                        >
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="consumer_secret" class="form-label">Consumer Secret</label>
                        <input 
                            type="text" 
                            id="consumer_secret" 
                            class="form-control" 
                            placeholder="cs_xxxxxxxxxx"
                            value="{{ $consumerSecret ?? '' }}"
                            {{ !empty($consumerSecret) ? '' : '' }}
                        >
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <button class="btn btn-sm btn-outline-primary" id="testApiBtn" {{ (empty($consumerKey) || empty($consumerSecret) || !empty($isActive)) ? 'disabled' : '' }}>
                    <span id="testBtnText">Test Connection</span>
                    <span id="testBtnSpinner" class="spinner-border spinner-border-sm ms-2" style="display:none;"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- API Test Response --}}
    <div id="apiTestSection" class="mt-3" style="display:{{ !empty($isActive) ? 'block' : 'none' }};">
        <div class="row align-items-center">

            <!-- Status Card -->
            <div class="col-12 col-md-6 mb-3 mb-md-0">
                <div class="shadow-sm border p-2 text-center">
                    <h6 class="mb-3" style="font-size: 0.9rem; font-weight: 600;">
                        API Test Status
                    </h6>

                    <div
                        id="apiTestStatus"
                        class="d-flex align-items-center justify-content-center mx-auto mb-3"
                        style="
                            width: 120px;
                            height: 120px;
                            border-radius: 50%;
                            font-size: 2rem;
                            font-weight: bold;
                            background-color: {{ !empty($isActive) ? '#d4edda' : '' }};
                            color: {{ !empty($isActive) ? '#155724' : '' }};
                            border: {{ !empty($isActive) ? '3px solid #c3e6cb' : '' }};
                        ">
                        {{ !empty($isActive) ? '200' : '' }}
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="col-12 col-md-6 text-center text-md-end">
                @if(empty($isActive))
                <button
                    class="btn btn-sm btn-outline-primary"
                    id="saveBtn"
                    style="display:none;">
                    Save Configuration
                </button>
                @else
                <div class="alert alert-success mb-0">
                    <i class="fa-solid fa-circle-check"></i> Configuration Already Saved
                </div>
                @endif
            </div>

        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// Enable test button when both keys are filled
document.getElementById('consumer_key').addEventListener('input', checkApiFields);
document.getElementById('consumer_secret').addEventListener('input', checkApiFields);

function checkApiFields() {
    const key = document.getElementById('consumer_key').value.trim();
    const secret = document.getElementById('consumer_secret').value.trim();
    const testApiBtn = document.getElementById('testApiBtn');
    const consumerKeyInput = document.getElementById('consumer_key');
    const consumerSecretInput = document.getElementById('consumer_secret');
    
    // Only enable if both fields have values AND fields are not disabled
    if (!consumerKeyInput.disabled && !consumerSecretInput.disabled) {
        testApiBtn.disabled = !(key && secret);
    }
}

// Website Check
document.getElementById('checkBtn').addEventListener('click', async function () {
    const url = document.getElementById('website_url').value;
    const previewSection = document.getElementById('previewSection');
    const apiConfigSection = document.getElementById('apiConfigSection');
    const statusMessage = document.getElementById('statusMessage');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');
    const checkBtn = document.getElementById('checkBtn');
    const websiteUrlInput = document.getElementById('website_url');

    // Reset
    statusMessage.innerHTML = '';
    statusMessage.className = 'mt-4 fw-bold';
    previewSection.style.display = 'none';
    apiConfigSection.style.display = 'none';
    document.getElementById('apiTestSection').style.display = 'none';

    if (!url) {
        alert('Please enter a website URL');
        return;
    }

    // Basic URL validation
    try {
        new URL(url);
    } catch (e) {
        alert('Please enter a valid URL (must include http:// or https://)');
        return;
    }

    // Show loading state
    checkBtn.disabled = true;
    btnSpinner.style.display = 'inline-block';

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
            document.getElementById('websiteTitle').textContent = data.title || 'No title found';
            document.getElementById('websiteDescription').textContent = data.description || 'No description available';
            document.getElementById('websiteLink').href = url;
            document.getElementById('websiteLinkText').textContent = url;

            // Show preview image if available (priority)
            if (data.image) {
                document.getElementById('previewImage').src = data.image;
                document.getElementById('previewImage').style.display = 'block';
                document.getElementById('placeholderIcon').style.display = 'none';
            } 
            // Otherwise show favicon
            else if (data.favicon) {
                document.getElementById('websiteFavicon').src = data.favicon;
                document.getElementById('websiteFavicon').style.display = 'block';
                document.getElementById('placeholderIcon').style.display = 'none';
            }

            // Display response JSON
            const statusCode = response.status;
            const statusEl = document.getElementById('websiteStatus');
            statusEl.textContent = statusCode;
            
            // Color based on status code
            if (statusCode >= 200 && statusCode < 300) {
                statusEl.style.backgroundColor = '#d4edda';
                statusEl.style.color = '#155724';
                statusEl.style.border = '3px solid #c3e6cb';
            } else if (statusCode >= 300 && statusCode < 400) {
                statusEl.style.backgroundColor = '#fff3cd';
                statusEl.style.color = '#856404';
                statusEl.style.border = '3px solid #ffeeba';
            } else if (statusCode >= 400 && statusCode < 500) {
                statusEl.style.backgroundColor = '#f8d7da';
                statusEl.style.color = '#721c24';
                statusEl.style.border = '3px solid #f5c6cb';
            } else {
                statusEl.style.backgroundColor = '#f8d7da';
                statusEl.style.color = '#721c24';
                statusEl.style.border = '3px solid #f5c6cb';
            }

            previewSection.style.display = 'block';
            
            // Show API config section if status is 200
            if (response.status === 200) {
                apiConfigSection.style.display = 'block';
                statusMessage.innerHTML = 'Website verified successfully! Please configure WooCommerce API keys below.';
                statusMessage.classList.add('text-success');
                
                // DISABLE website URL field and Check button
                websiteUrlInput.disabled = true;
                checkBtn.disabled = true;
            } else {
                statusMessage.innerHTML = 'Website found but verification incomplete.';
                statusMessage.classList.add('text-warning');
            }
        } else {
            // Error message
            statusMessage.innerHTML = data.message || 'Unable to verify website';
            statusMessage.classList.add('text-danger');
            
            // Display error status
            const statusEl = document.getElementById('websiteStatus');
            statusEl.textContent = response.status || 'Error';
            statusEl.style.backgroundColor = '#f8d7da';
            statusEl.style.color = '#721c24';
            statusEl.style.border = '3px solid #f5c6cb';
            previewSection.style.display = 'block';
        }

    } catch (error) {
        statusMessage.innerHTML = 'Network error. Please try again.';
        statusMessage.classList.add('text-danger');
    } finally {
        // Reset button state only if not status 200
        if (!websiteUrlInput.disabled) {
            checkBtn.disabled = false;
        }
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
    const apiTestSection = document.getElementById('apiTestSection');
    const saveBtn = document.getElementById('saveBtn');
    const consumerKeyInput = document.getElementById('consumer_key');
    const consumerSecretInput = document.getElementById('consumer_secret');

    // Reset
    apiTestSection.style.display = 'none';
    saveBtn.style.display = 'none';

    // Show loading
    testApiBtn.disabled = true;
    testBtnSpinner.style.display = 'inline-block';

    try {
        // Construct WooCommerce API test URL (system_status endpoint)
        const baseUrl = url.replace(/\/$/, ''); // Remove trailing slash
        const testEndpoint = `${baseUrl}/wp-json/wc/v3/system_status`;
        
        // Create authorization header (Basic Auth)
        const auth = btoa(`${consumerKey}:${consumerSecret}`);
        
        const response = await fetch(testEndpoint, {
            method: 'GET',
            headers: {
                'Authorization': `Basic ${auth}`,
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();

        // Display status code with color
        const statusEl = document.getElementById('apiTestStatus');
        statusEl.textContent = response.status;
        
        if (response.ok) {
            statusEl.style.backgroundColor = '#d4edda';
            statusEl.style.color = '#155724';
            statusEl.style.border = '3px solid #c3e6cb';
            saveBtn.style.display = 'inline-block';
            
            // DISABLE consumer key, consumer secret, and test button
            consumerKeyInput.disabled = true;
            consumerSecretInput.disabled = true;
            testApiBtn.disabled = true;
        } else {
            statusEl.style.backgroundColor = '#f8d7da';
            statusEl.style.color = '#721c24';
            statusEl.style.border = '3px solid #f5c6cb';
        }
        
        apiTestSection.style.display = 'block';

    } catch (error) {
        const statusEl = document.getElementById('apiTestStatus');
        statusEl.textContent = 'Error';
        statusEl.style.backgroundColor = '#f8d7da';
        statusEl.style.color = '#721c24';
        statusEl.style.border = '3px solid #f5c6cb';
        apiTestSection.style.display = 'block';
    } finally {
        // Reset button state only if fields are not disabled
        if (!consumerKeyInput.disabled && !consumerSecretInput.disabled) {
            testApiBtn.disabled = false;
        }
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

        if (response.ok && data.success && data.redirect) {
            // ✅ Redirect to integration show page
            window.location.href = data.redirect;
        } else {
            saveBtn.disabled = false;
            saveBtn.innerHTML = 'Save Configuration';
        }

    } catch (error) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = 'Save Configuration';
    }
});

</script>
@endpush