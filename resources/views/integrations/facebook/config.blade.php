@extends(app('app.layout'))
@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make(['Configure', ($integration->name ?? 'Integration'), 'Facebook Page', 'Integration']))
@section('meta-description', \Iquesters\Foundation\Helpers\MetaHelper::description('Configure page of Integration'))
@php
use Illuminate\Support\Str;
$tabs = [
    ['route' => 'integration.show', 'params' => ['integrationUid' => $integration->uid], 'icon' => 'far fa-fw fa-list-alt', 'label' => 'Overview'],
    ['route' => 'integration.configure', 'params' => ['integrationUid' => $integration->uid], 'icon' => 'fas fa-fw fa-sliders-h', 'label' => 'Configure'],
    ['route' => 'integration.apiconf', 'params' => ['integrationUid' => $integration->uid], 'icon' => 'fas fa-fw fa-screwdriver-wrench', 'label' => 'Api Conf'],
    ['route' => 'integration.syncdata', 'params' => ['integrationUid' => $integration->uid], 'icon' => 'fas fa-fw fa-rotate', 'label' => 'Sync Data'],
];
$hiddenMetaKeys = ['chatbot_vector'];
$displayMetas = $integration->metas->reject(fn ($meta) => in_array($meta->meta_key, $hiddenMetaKeys, true));
$facebookPageName = $integration->getMeta('facebook_page_name');
$facebookPageId = $integration->getMeta('facebook_page_id');
$isFacebookConnected = !empty($facebookPageId);
$apiUtilBaseUrl = trim((string) ($integration->supportedInt?->getMeta('facebook_api_url') ?: config('integration.api_util_base_url', '')));
$facebookAppId = trim((string) (
    $integration->getMeta('facebook_app_id')
    ?: $integration->supportedInt?->getMeta('facebook_app_id')
    ?: config('integration.facebook_app_id', '1466862378012896')
));
$isFacebookBackendConfigured = $apiUtilBaseUrl !== '' || $facebookAppId !== '';
@endphp
@section('content')
<div class="d-flex align-items-center justify-content-start gap-2 mb-3">
    <h5 class="mb-0 text-muted">
        {{ $integration->name }}
        {!! $integration->supportedInt?->getMeta('icon') !!}
    </h5>
    <x-userinterface::status :status="$integration->status" />
</div>
<div class="border rounded p-3 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h6 class="mb-1">Facebook Page Configuration</h6>
            <div class="text-muted small">{{ $integration->supportedInt?->getMeta('description') }}</div>
        </div>
        <span class="badge {{ $isFacebookConnected ? 'bg-success' : 'bg-primary' }}">
            {{ $isFacebookConnected ? 'Connected' : 'Facebook Page' }}
        </span>
    </div>
</div>
<div class="border rounded p-3 mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div>
            <h6 class="mb-1">Connect Facebook</h6>
            <p class="text-muted small mb-0">
                Start Facebook onboarding, choose a page, and save the selected page credentials for this integration.
            </p>
            @if ($isFacebookConnected)
                <div class="text-success small mt-2">
                    <i class="fa-solid fa-circle-check me-1"></i>
                    Connected to {{ $facebookPageName ?: 'Facebook page' }}{{ $facebookPageId ? ' ('.$facebookPageId.')' : '' }}.
                </div>
            @endif
            @unless ($isFacebookBackendConfigured)
                <div class="text-danger small mt-2">
                    Set INTEGRATION_API_UTIL_BASE_URL in messenger/.env to enable Facebook onboarding.
                </div>
            @endunless
        </div>
        <button
            type="button"
            class="btn btn-primary {{ $isFacebookBackendConfigured ? '' : 'disabled' }}"
            id="facebookConnectBtn"
            data-connect-url="{{ route('social.facebook.connect.start') }}"
            data-pages-url="{{ route('social.facebook.pages') }}"
            data-save-url="{{ route('social.facebook.integration.save') }}"
            data-integration-id="{{ $integration->uid }}"
            data-display-name="{{ $integration->name }}"
            data-facebook-app-id="{{ $facebookAppId }}"
            data-csrf-token="{{ csrf_token() }}"
            {{ $isFacebookBackendConfigured ? '' : 'disabled' }}
        >
            <i class="fa-brands fa-facebook me-1"></i>
            <span id="facebookConnectBtnText">{{ $isFacebookConnected ? 'Reconnect Facebook' : 'Connect Facebook' }}</span>
            <span id="facebookConnectSpinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
        </button>
    </div>
    <div id="facebookConnectAlert" class="alert mt-3 mb-0 d-none" role="alert"></div>
</div>
<div class="border rounded p-3 mb-3 d-none" id="facebookPageSelectPanel">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h6 class="mb-1">Select Facebook Page</h6>
            <div class="text-muted small">Choose the page to link with this integration.</div>
        </div>
        <span class="spinner-border spinner-border-sm d-none" id="facebookPagesSpinner" role="status" aria-hidden="true"></span>
    </div>
    <div id="facebookPagesList" class="list-group"></div>
</div>
<div class="border rounded p-3 mb-3">
    <h6 class="mb-3">Onboarding Flow</h6>
    <div class="row g-3">
        <div class="col-md-3">
            <div class="text-muted small">1. Start</div>
            <div class="fw-semibold">Create secure state</div>
        </div>
        <div class="col-md-3">
            <div class="text-muted small">2. Authorize</div>
            <div class="fw-semibold">Facebook login and consent</div>
        </div>
        <div class="col-md-3">
            <div class="text-muted small">3. Select Page</div>
            <div class="fw-semibold">Choose available page</div>
        </div>
        <div class="col-md-3">
            <div class="text-muted small">4. Save</div>
            <div class="fw-semibold">Store final page integration</div>
        </div>
    </div>
</div>
<div class="border rounded p-3">
    <h6 class="mb-3">Saved Configuration</h6>
    @forelse ($displayMetas as $meta)
    @php
    $isSecret = Str::contains($meta->meta_key, ['token', 'key', 'secret', 'password']);
    $displayValue = $isSecret ? Str::mask($meta->meta_value, '*', 0, max(strlen($meta->meta_value) - 4, 0)) : $meta->meta_value;
    @endphp
    <div class="row g-2 align-items-start mb-2">
        <div class="col-md-3 text-muted">{{ Str::headline($meta->meta_key) }}</div>
        <div class="col-md-9 text-break"><code>{{ $displayValue }}</code></div>
    </div>
    @empty
    <div class="text-muted small mb-0">No Facebook Page configuration has been saved yet.</div>
    @endforelse
</div>
<div class="modal fade" id="facebookLoginModal" tabindex="-1" aria-labelledby="facebookLoginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="facebookLoginModalLabel">Facebook Login</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="facebookLoginModalMessage">
                    Facebook login is ready. Continue in a secure Facebook window to choose your page.
                </p>
                <button type="button" class="btn btn-primary w-100" id="facebookContinueBtn">
                    <i class="fa-brands fa-facebook me-1"></i>
                    Continue with Facebook
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const callbackParams = new URLSearchParams(window.location.search);
    const callbackState = callbackParams.get('state') || callbackParams.get('facebook_state');
    const callbackError = callbackParams.get('error') || callbackParams.get('error_description');

    if (callbackParams.get('facebook_popup') === '1') {
        const callbackTarget = window.opener && !window.opener.closed ? window.opener : window.parent;

        callbackTarget.postMessage({
            type: 'facebook_oauth_callback',
            state: callbackState,
            error: callbackError
        }, window.location.origin);

        document.body.innerHTML = '<div style="font-family: sans-serif; padding: 24px;">Facebook login completed. You can close this window.</div>';
        if (window.opener && !window.opener.closed) {
            window.close();
        }

        return;
    }

    const connectButton = document.getElementById('facebookConnectBtn');
    const connectButtonText = document.getElementById('facebookConnectBtnText');
    const connectSpinner = document.getElementById('facebookConnectSpinner');
    const alertBox = document.getElementById('facebookConnectAlert');
    const pageSelectPanel = document.getElementById('facebookPageSelectPanel');
    const pagesSpinner = document.getElementById('facebookPagesSpinner');
    const pagesList = document.getElementById('facebookPagesList');
    const loginModal = document.getElementById('facebookLoginModal');
    const loginModalMessage = document.getElementById('facebookLoginModalMessage');
    const facebookContinueButton = document.getElementById('facebookContinueBtn');
    let facebookAuthorizationUrl = '';
    let facebookLoginWindow = null;
    let facebookLoginPoll = null;
    let facebookSdkReady = null;

    if (!connectButton || !alertBox) {
        return;
    }

    const csrfToken = connectButton.dataset.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const facebookApiVersion = 'v25.0';
    const facebookPermissions = 'pages_show_list,pages_manage_posts,pages_read_engagement,business_management';

    const setAlert = (type, message) => {
        alertBox.className = `alert mt-3 mb-0 alert-${type}`;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    };

    const clearAlert = () => {
        alertBox.className = 'alert mt-3 mb-0 d-none';
        alertBox.textContent = '';
    };

    const getErrorMessage = (data) => {
        const attemptedIds = Array.isArray(data.attempted_integration_ids)
            ? data.attempted_integration_ids.map((attempt) => attempt.integration_id).join(', ')
            : '';
        const suffix = attemptedIds ? ` Tried integration_id: ${attemptedIds}` : '';

        if (typeof data.message === 'string') {
            return `${data.message}${suffix}`;
        }

        if (typeof data.detail === 'string') {
            return `${data.detail}${suffix}`;
        }

        if (Array.isArray(data.detail) && data.detail.length > 0) {
            return data.detail
                .map((item) => item.msg || item.message || JSON.stringify(item))
                .join(' ') + suffix;
        }

        return `Unable to start Facebook onboarding. Please try again.${suffix}`;
    };

    const setLoading = (isLoading) => {
        connectButton.disabled = isLoading;
        connectSpinner.classList.toggle('d-none', !isLoading);
        connectButtonText.textContent = isLoading ? 'Connecting...' : '{{ $isFacebookConnected ? 'Reconnect Facebook' : 'Connect Facebook' }}';
    };

    const normalizePages = (data) => {
        const rawPages = data.pages || data.data || data.result?.pages || [];
        return Array.isArray(rawPages) ? rawPages : [];
    };

    const getPageId = (page) => page.page_id || page.id || page.uid || '';
    const getPageName = (page) => page.page_name || page.name || page.display_name || 'Facebook Page';
    const getAuthorizationUrl = (data) => data.authorization_url || data.auth_url || data.redirect_url || data.url || '';
    const getFacebookPopupPosition = () => {
        const width = 560;
        const height = 720;
        const browserLeft = typeof window.screenLeft === 'number'
            ? window.screenLeft
            : (typeof window.screenX === 'number' ? window.screenX : 0);
        const browserTop = typeof window.screenTop === 'number'
            ? window.screenTop
            : (typeof window.screenY === 'number' ? window.screenY : 0);
        const browserWidth = window.outerWidth || window.innerWidth || document.documentElement.clientWidth || window.screen.width;
        const browserHeight = window.outerHeight || window.innerHeight || document.documentElement.clientHeight || window.screen.height;
        const left = Math.round(browserLeft + ((browserWidth - width) / 2));
        const top = Math.round(browserTop + ((browserHeight - height) / 2));

        return {
            width,
            height,
            left,
            top,
        };
    };
    const getFacebookPopupFeatures = ({ width, height, left, top }) => {
        return `popup=yes,width=${width},height=${height},left=${left},top=${top},screenX=${left},screenY=${top},menubar=no,toolbar=no,location=yes,status=no,scrollbars=yes,resizable=yes`;
    };
    const openFacebookPopup = (url = 'about:blank') => {
        const position = getFacebookPopupPosition();

        const popup = window.open(url, `facebookLogin_${Date.now()}`, getFacebookPopupFeatures(position));

        if (popup) {
            popup.resizeTo(position.width, position.height);
            popup.moveTo(position.left, position.top);
        }

        return popup;
    };
    const getPopupRedirectTarget = () => {
        const redirectTarget = new URL(window.location.href);
        redirectTarget.searchParams.set('facebook_popup', '1');
        redirectTarget.searchParams.delete('state');
        redirectTarget.searchParams.delete('facebook_state');
        redirectTarget.searchParams.delete('error');
        redirectTarget.searchParams.delete('error_description');

        return redirectTarget.toString();
    };

    const clearFacebookLoginPoll = () => {
        if (facebookLoginPoll) {
            clearInterval(facebookLoginPoll);
            facebookLoginPoll = null;
        }
    };

    const setLoginModalMessage = (message) => {
        if (loginModalMessage) {
            loginModalMessage.textContent = message;
        }
    };

    const setContinueButtonLoading = (isLoading) => {
        if (facebookContinueButton) {
            facebookContinueButton.disabled = isLoading;
            facebookContinueButton.innerHTML = isLoading
                ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Opening Facebook...'
                : '<i class="fa-brands fa-facebook me-1"></i>Continue with Facebook';
        }
    };

    const setPagesLoading = (isLoading) => {
        if (pagesSpinner) {
            pagesSpinner.classList.toggle('d-none', !isLoading);
        }
    };

    const showLoginModal = () => {
        if (!loginModal) {
            return;
        }

        if (window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(loginModal).show();
            return;
        }

        loginModal.classList.add('show');
        loginModal.style.display = 'block';
        loginModal.removeAttribute('aria-hidden');
        loginModal.setAttribute('aria-modal', 'true');
        document.body.classList.add('modal-open');
    };

    const hideLoginModal = () => {
        if (!loginModal) {
            return;
        }

        if (window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(loginModal).hide();
        } else {
            loginModal.classList.remove('show');
            loginModal.style.display = 'none';
            loginModal.setAttribute('aria-hidden', 'true');
            loginModal.removeAttribute('aria-modal');
            document.body.classList.remove('modal-open');
        }

        clearFacebookLoginPoll();
    };

    loginModal?.querySelectorAll('[data-bs-dismiss="modal"], [data-dismiss="modal"]').forEach((button) => {
        button.addEventListener('click', hideLoginModal);
    });

    loginModal?.addEventListener('hidden.bs.modal', clearFacebookLoginPoll);

    const ensureFacebookSdk = () => {
        if (window.FB) {
            return Promise.resolve(window.FB);
        }

        if (facebookSdkReady) {
            return facebookSdkReady;
        }

        facebookSdkReady = new Promise((resolve, reject) => {
            const appId = connectButton.dataset.facebookAppId || '';

            if (!appId) {
                reject(new Error('Facebook App ID is not configured.'));
                return;
            }

            window.fbAsyncInit = function () {
                window.FB.init({
                    appId,
                    cookie: true,
                    xfbml: false,
                    version: facebookApiVersion
                });
                resolve(window.FB);
            };

            if (document.getElementById('facebook-jssdk')) {
                return;
            }

            const firstScript = document.getElementsByTagName('script')[0];
            const script = document.createElement('script');
            script.id = 'facebook-jssdk';
            script.async = true;
            script.defer = true;
            script.crossOrigin = 'anonymous';
            script.src = 'https://connect.facebook.net/en_US/sdk.js';
            script.onerror = () => reject(new Error('Unable to load Facebook SDK.'));
            firstScript.parentNode.insertBefore(script, firstScript);
        });

        return facebookSdkReady;
    };

    const facebookApi = (path) => new Promise((resolve, reject) => {
        window.FB.api(path, (response) => {
            if (!response || response.error) {
                reject(response?.error || new Error('Facebook API request failed.'));
                return;
            }

            resolve(response);
        });
    });

    const startFacebookLoginPoll = () => {
        clearFacebookLoginPoll();
        facebookLoginPoll = setInterval(async () => {
            if (facebookLoginWindow?.closed) {
                clearFacebookLoginPoll();
                setContinueButtonLoading(false);
                setLoginModalMessage('Facebook login is ready. Continue in a secure Facebook window to choose your page.');
                setAlert('warning', 'Facebook login window was closed. Click "Connect Facebook" to try again.');
                return;
            }

            try {
                const response = await fetch(window.location.href, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const text = await response.text();

                if (text.includes('facebook_page_id') || text.includes('Connected to')) {
                    clearFacebookLoginPoll();
                    hideLoginModal();
                    setContinueButtonLoading(false);
                    window.location.reload();
                }
            } catch (error) {
                // Ignore polling errors and keep checking until the popup closes.
            }
        }, 2000);
    };

    const saveSelectedPage = async (state, page, context = {}) => {
        const pageId = getPageId(page);
        const pageName = getPageName(page);

        if (!pageId) {
            setAlert('danger', 'Selected Facebook page is missing a page id.');
            return;
        }

        clearAlert();
        setPagesLoading(true);

        const payload = {
            page_id: pageId,
            page_name: pageName,
            integration_id: connectButton.dataset.integrationId
        };

        if (state) {
            payload.state = state;
        }

        if (page.access_token) {
            payload.page_access_token = page.access_token;
        }

        if (context.userAccessToken) {
            payload.user_access_token = context.userAccessToken;
        }

        try {
            const response = await fetch(connectButton.dataset.saveUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                setAlert('danger', getErrorMessage(data));
                return;
            }

            setAlert('success', 'Facebook page connected successfully.');
            if (window.opener && !window.opener.closed) {
                window.opener.location.href = data.redirect || window.location.pathname;
                window.close();
                return;
            }

            window.location.href = data.redirect || window.location.pathname;
        } catch (error) {
            setAlert('danger', 'Network error while saving Facebook page. Please try again.');
        } finally {
            setPagesLoading(false);
        }
    };

    const renderPages = (state, pages, context = {}) => {
        if (!pageSelectPanel || !pagesList) {
            return;
        }

        pageSelectPanel.classList.remove('d-none');
        pagesList.innerHTML = '';

        if (pages.length === 0) {
            pagesList.innerHTML = '<div class="text-muted small">No Facebook pages were returned for this account.</div>';
            return;
        }

        pages.forEach((page) => {
            const pageId = getPageId(page);
            const pageName = getPageName(page);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3';
            button.innerHTML = `
                <span>
                    <span class="fw-semibold d-block"></span>
                    <small class="text-muted"></small>
                </span>
                <span class="btn btn-sm btn-outline-primary">Select</span>
            `;
            button.querySelector('.fw-semibold').textContent = pageName;
            button.querySelector('small').textContent = pageId;
            button.addEventListener('click', () => saveSelectedPage(state, page, context));
            pagesList.appendChild(button);
        });
    };

    const loadPagesFromFacebookLogin = async (loginResponse) => {
        if (!loginResponse.authResponse) {
            setAlert('warning', 'Facebook login was cancelled or permissions were not granted.');
            return;
        }

        clearAlert();
        setPagesLoading(true);
        pageSelectPanel?.classList.remove('d-none');

        try {
            const accounts = await facebookApi('/me/accounts?fields=id,name,access_token&limit=100');
            renderPages(null, normalizePages(accounts), {
                userAccessToken: loginResponse.authResponse.accessToken
            });
            setAlert('info', 'Choose the Facebook page to connect.');
        } catch (error) {
            setAlert('danger', error?.message || 'Unable to load Facebook pages from Facebook.');
        } finally {
            setPagesLoading(false);
        }
    };

    const loadFacebookPages = async (state) => {
        if (!state) {
            return;
        }

        clearAlert();
        setPagesLoading(true);
        pageSelectPanel?.classList.remove('d-none');

        try {
            const url = new URL(connectButton.dataset.pagesUrl, window.location.origin);
            url.searchParams.set('state', state);

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json'
                }
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                setAlert('danger', getErrorMessage(data));
                return;
            }

            renderPages(state, normalizePages(data));
        } catch (error) {
            setAlert('danger', 'Network error while loading Facebook pages. Please try again.');
        } finally {
            setPagesLoading(false);
        }
    };

    const onFacebookMessage = (event) => {
        if (event.origin !== window.location.origin || event.data?.type !== 'facebook_oauth_callback') {
            return;
        }

        window.removeEventListener('message', onFacebookMessage);
        clearFacebookLoginPoll();
        hideLoginModal();
        setLoading(false);
        setContinueButtonLoading(false);

        if (event.data.error) {
            setAlert('danger', event.data.error);
            return;
        }

        if (!event.data.state) {
            setAlert('danger', 'Facebook login completed, but no onboarding state was returned.');
            return;
        }

        clearAlert();
        loadFacebookPages(event.data.state);
    };

    facebookContinueButton?.addEventListener('click', function () {
        if (!facebookAuthorizationUrl) {
            setAlert('danger', 'Facebook authorization URL is not ready yet. Please try again.');
            return;
        }

        setContinueButtonLoading(true);

        facebookLoginWindow = openFacebookPopup();

        if (!facebookLoginWindow) {
            setContinueButtonLoading(false);
            setAlert('danger', 'Popup blocked. Please allow popups for this site and try again.');
            return;
        }

        facebookLoginWindow.location.href = facebookAuthorizationUrl;
        facebookLoginWindow.focus();
        setLoginModalMessage('Complete the Facebook login in the opened window.');
        setAlert('info', 'Facebook login opened. Complete the login there to continue.');
        startFacebookLoginPoll();
    });

    connectButton.addEventListener('click', async function () {
        clearAlert();
        setLoading(true);
        facebookAuthorizationUrl = '';
        facebookLoginWindow = openFacebookPopup();

        if (!facebookLoginWindow) {
            setLoading(false);
            setAlert('danger', 'Popup blocked. Please allow popups for this site and try again.');
            return;
        }

        facebookLoginWindow.document.body.innerHTML = '<div style="font-family: sans-serif; padding: 24px;">Preparing Facebook login...</div>';
        facebookLoginWindow.focus();
        setAlert('info', 'Preparing Facebook login...');

        try {
            const response = await fetch(connectButton.dataset.connectUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    integration_id: connectButton.dataset.integrationId,
                    display_name: connectButton.dataset.displayName,
                    redirect_target: getPopupRedirectTarget()
                })
            });

            const data = await response.json().catch(() => ({}));
            const authorizationUrl = getAuthorizationUrl(data);

            if (!response.ok) {
                setAlert('danger', getErrorMessage(data));
                facebookLoginWindow.close();
                return;
            }

            if (!authorizationUrl) {
                setAlert('danger', 'Facebook onboarding started, but no authorization URL was returned.');
                facebookLoginWindow.close();
                return;
            }

            facebookAuthorizationUrl = authorizationUrl;
            window.addEventListener('message', onFacebookMessage);
            facebookLoginWindow.location.href = facebookAuthorizationUrl;
            facebookLoginWindow.focus();
            setAlert('info', 'Facebook login opened. Complete the login there to continue.');
            startFacebookLoginPoll();
        } catch (error) {
            setAlert('danger', 'Network error while starting Facebook onboarding. Please try again.');
            facebookLoginWindow.close();
        } finally {
            setLoading(false);
        }
    });

    const urlParams = new URLSearchParams(window.location.search);
    const onboardingState = urlParams.get('state') || urlParams.get('facebook_state');

    if (onboardingState) {
        loadFacebookPages(onboardingState);
    }

    ensureFacebookSdk().catch(() => {});
});
</script>
@endpush
