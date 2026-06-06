@extends(app('app.layout'))

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make(['Complete Facebook Connection']))
@section('meta-description', \Iquesters\Foundation\Helpers\MetaHelper::description('Select a Facebook Page to complete the integration'))

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="mb-1 text-muted">Select Facebook Page</h5>
        <div class="text-muted small">Choose the page to connect with this integration.</div>
    </div>
    <a href="{{ $integrationsUrl }}" class="btn btn-sm btn-outline-dark">Back to integrations</a>
</div>

<div id="facebookCompleteAlert" class="alert {{ $initialError ? 'alert-danger' : 'd-none' }}" role="alert">
    {{ $initialError }}
</div>

@if (! $initialError)
    <div id="facebookPagesLoading" class="border rounded p-3 mb-3">
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        Loading Facebook Pages...
    </div>

    <div id="facebookPagesPanel" class="d-none">
        <div id="facebookPagesList" class="row g-3"></div>
    </div>

    <div id="facebookSuccessPanel" class="alert alert-success d-none" role="alert"></div>

    <div class="d-flex gap-2 mt-3">
        <button type="button" id="facebookRetryBtn" class="btn btn-sm btn-outline-primary d-none">
            Retry
        </button>
        <a href="{{ $integrationsUrl }}" id="facebookReconnectLink" class="btn btn-sm btn-outline-dark d-none">
            Start again
        </a>
    </div>
@endif
@endsection

@push('scripts')
@if (! $initialError)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const state = @json($state);
    const pagesUrl = @json($pagesUrl);
    const saveUrl = @json($saveUrl);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const alertBox = document.getElementById('facebookCompleteAlert');
    const loadingBox = document.getElementById('facebookPagesLoading');
    const pagesPanel = document.getElementById('facebookPagesPanel');
    const pagesList = document.getElementById('facebookPagesList');
    const successPanel = document.getElementById('facebookSuccessPanel');
    const retryButton = document.getElementById('facebookRetryBtn');
    const reconnectLink = document.getElementById('facebookReconnectLink');

    const normalizePages = (data) => {
        const pages = data.pages || data.data || data.result?.pages || [];
        return Array.isArray(pages) ? pages : [];
    };

    const pageId = (page) => page.page_id || page.id || page.uid || '';
    const pageName = (page) => page.page_name || page.name || page.display_name || 'Facebook Page';
    const pageCategory = (page) => page.category || page.page_category || '';
    const pageTasks = (page) => Array.isArray(page.tasks) ? page.tasks : [];

    const showAlert = (type, message, canRetry = false, canReconnect = false) => {
        alertBox.className = `alert alert-${type}`;
        alertBox.textContent = message;
        reconnectLink.textContent = 'Start again';
        retryButton.classList.toggle('d-none', !canRetry);
        reconnectLink.classList.toggle('d-none', !canReconnect);
    };

    const showLoading = (isLoading, message = 'Loading Facebook Pages...') => {
        loadingBox.classList.toggle('d-none', !isLoading);
        loadingBox.innerHTML = isLoading
            ? `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${message}`
            : '';
    };

    const safeErrorMessage = (data, fallback) => {
        return typeof data.message === 'string' && data.message !== '' ? data.message : fallback;
    };

    const renderPages = (pages) => {
        pagesPanel.classList.remove('d-none');
        pagesList.innerHTML = '';

        pages.forEach((page) => {
            const id = pageId(page);
            const name = pageName(page);
            const category = pageCategory(page);
            const tasks = pageTasks(page);
            const col = document.createElement('div');

            col.className = 'col-md-6 col-xl-4';
            col.innerHTML = `
                <div class="border rounded p-3 h-100 d-flex flex-column gap-2">
                    <div>
                        <div class="fw-semibold facebook-page-name"></div>
                        <div class="text-muted small facebook-page-category d-none"></div>
                        <div class="text-muted small text-break facebook-page-id"></div>
                    </div>
                    <div class="facebook-page-tasks d-none"></div>
                    <button type="button" class="btn btn-sm btn-primary mt-auto facebook-page-select">
                        Select Page
                    </button>
                </div>
            `;

            col.querySelector('.facebook-page-name').textContent = name;
            col.querySelector('.facebook-page-id').textContent = id;

            if (category) {
                const categoryEl = col.querySelector('.facebook-page-category');
                categoryEl.textContent = category;
                categoryEl.classList.remove('d-none');
            }

            if (tasks.length > 0) {
                const tasksEl = col.querySelector('.facebook-page-tasks');
                tasksEl.className = 'facebook-page-tasks d-flex flex-wrap gap-1';

                tasks.forEach((task) => {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-secondary-subtle text-secondary-emphasis border';
                    badge.textContent = task;
                    tasksEl.appendChild(badge);
                });
            }

            col.querySelector('.facebook-page-select').addEventListener('click', function () {
                saveSelectedPage(id, name);
            });

            pagesList.appendChild(col);
        });
    };

    const loadPages = async () => {
        showLoading(true);
        pagesPanel.classList.add('d-none');
        successPanel.classList.add('d-none');
        alertBox.className = 'alert d-none';
        retryButton.classList.add('d-none');
        reconnectLink.classList.add('d-none');

        try {
            const url = new URL(pagesUrl, window.location.origin);
            url.searchParams.set('state', state);

            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json'
                }
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const retry = response.status === 409 || response.status >= 500;
                const reconnect = response.status === 404 || response.status === 410;
                showAlert('danger', safeErrorMessage(data, 'Facebook setup is temporarily unavailable. Please try again.'), retry, reconnect);
                return;
            }

            const pages = normalizePages(data);

            if (pages.length === 0) {
                showAlert('warning', 'No Facebook Pages were returned for this account.', true, true);
                return;
            }

            renderPages(pages);
        } catch (error) {
            showAlert('danger', 'Facebook setup is temporarily unavailable. Please try again.', true, false);
        } finally {
            showLoading(false);
        }
    };

    const saveSelectedPage = async (selectedPageId, selectedPageName) => {
        if (!selectedPageId) {
            showAlert('danger', 'Selected page is no longer available. Please choose another page or reconnect.', false, true);
            return;
        }

        showLoading(true, 'Connecting selected Facebook Page...');
        alertBox.className = 'alert d-none';
        pagesList.querySelectorAll('button').forEach((button) => {
            button.disabled = true;
        });

        try {
            const response = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    state: state,
                    page_id: selectedPageId
                })
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                showAlert('danger', safeErrorMessage(data, 'Unable to save Facebook integration. Please try again.'), response.status >= 500, true);
                return;
            }

            const connectedPageName = data.page_name || data.page?.name || selectedPageName || 'Facebook Page';

            pagesPanel.classList.add('d-none');
            successPanel.textContent = `Facebook connected successfully: ${connectedPageName}`;
            successPanel.classList.remove('d-none');
            reconnectLink.textContent = 'Back to integrations';
            reconnectLink.classList.remove('d-none');
            setTimeout(() => { window.location.href = @json($integrationsUrl); }, 1500);
        } catch (error) {
            showAlert('danger', 'Facebook setup is temporarily unavailable. Please try again.', true, false);
        } finally {
            showLoading(false);
            pagesList.querySelectorAll('button').forEach((button) => {
                button.disabled = false;
            });
        }
    };

    retryButton.addEventListener('click', loadPages);
    loadPages();
});
</script>
@endif
@endpush
