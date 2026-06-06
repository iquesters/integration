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
        </div>
        <button
            type="button"
            class="btn btn-primary"
            id="facebookConnectBtn"
            data-connect-url="{{ route('social.facebook.connect.start') }}"
            data-integration-id="{{ $integration->uid }}"
            data-display-name="Facebook"
            data-csrf-token="{{ csrf_token() }}"
        >
            <i class="fa-brands fa-facebook me-1"></i>
            <span id="facebookConnectBtnText">{{ $isFacebookConnected ? 'Reconnect Facebook' : 'Connect Facebook' }}</span>
            <span id="facebookConnectSpinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
        </button>
    </div>
    <div id="facebookConnectAlert" class="alert mt-3 mb-0 d-none" role="alert"></div>
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const connectButton = document.getElementById('facebookConnectBtn');
    const connectButtonText = document.getElementById('facebookConnectBtnText');
    const connectSpinner = document.getElementById('facebookConnectSpinner');
    const alertBox = document.getElementById('facebookConnectAlert');

    if (!connectButton || !alertBox) {
        return;
    }

    const csrfToken = connectButton.dataset.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';

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
        if (typeof data.message === 'string') {
            return data.message;
        }

        if (typeof data.detail === 'string') {
            return data.detail;
        }

        return 'Unable to start Facebook onboarding. Please try again.';
    };

    const setLoading = (isLoading) => {
        connectButton.disabled = isLoading;
        connectSpinner.classList.toggle('d-none', !isLoading);
        connectButtonText.textContent = isLoading ? 'Connecting...' : '{{ $isFacebookConnected ? 'Reconnect Facebook' : 'Connect Facebook' }}';
    };

    const getAuthorizationUrl = (data) => data.authorization_url || data.auth_url || data.redirect_url || data.url || '';

    connectButton.addEventListener('click', async function () {
        clearAlert();
        setLoading(true);
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
                    display_name: connectButton.dataset.displayName || 'Facebook'
                })
            });

            const data = await response.json().catch(() => ({}));
            const authorizationUrl = getAuthorizationUrl(data);

            if (!response.ok) {
                setAlert('danger', getErrorMessage(data));
                return;
            }

            if (!authorizationUrl) {
                setAlert('danger', 'Facebook onboarding started, but no authorization URL was returned.');
                return;
            }

            window.location.assign(authorizationUrl);
        } catch (error) {
            setAlert('danger', 'Network error while starting Facebook onboarding. Please try again.');
        } finally {
            setLoading(false);
        }
    });
});
</script>
@endpush
