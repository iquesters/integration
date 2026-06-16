@extends(app('app.layout'))

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make([($integration->name ?? 'Integration'), 'gautams-chatbot', 'Integration']))
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
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-start gap-2 mb-3">
        <h5 class="mb-0 text-muted">
            {{ $integration->name }}
            {!! $integration->supportedInt?->getMeta('icon') !!}
        </h5>
        <x-userinterface::status :status="$integration->status" />
    </div>

    <table class="table table-bordered table-striped align-middle mb-4">
        <thead>
            <tr>
                <th>Purpose</th>
                <th>Method</th>
                <th>Path</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Submit inbound WhatsApp-style message (text)</td>
                <td><span class="badge bg-primary">POST</span></td>
                <td><code>/webhook/whatsapp/v1</code></td>
            </tr>
            <tr>
                <td>Poll for bot result by inbound message_id</td>
                <td><span class="badge bg-success">GET</span></td>
                <td><code>/messages/{message_id}/response</code></td>
            </tr>
        </tbody>
    </table>

    <hr class="my-3">

    <div class="mb-4">
        <form
            method="POST"
            action="{{ route('integration.configure.gautams-bot', ['integrationUid' => $integration->uid]) }}"
            id="gautamsBotConfigForm">
            @csrf
            <input type="hidden" name="human_handover_enabled" value="false">
            <input type="hidden" name="allow_internal_testing" value="false">

            <div class="d-flex flex-column gap-3">
                <div>
                    <h6 class="mb-1">Human Handover</h6>
                    <p class="text-muted small mb-0">
                        Enable manual human handover actions in Smart Messenger for this Gautams Chatbot integration.
                    </p>
                </div>

                <div class="d-flex align-items-center align-self-start gap-3">
                    <div class="form-check form-switch mb-0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="humanHandoverEnabled"
                            name="human_handover_enabled"
                            value="true"
                            {{ filter_var($humanHandoverEnabled ?? 'false', FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                        <label
                            class="form-check-label small fw-semibold d-inline-block"
                            for="humanHandoverEnabled"
                            style="min-width: 64px;">
                            {{ filter_var($humanHandoverEnabled ?? 'false', FILTER_VALIDATE_BOOLEAN) ? 'Enabled' : 'Disabled' }}
                        </label>
                    </div>
                </div>

                <div>
                    <h6 class="mb-1">Allow Internal Testing</h6>
                    <p class="text-muted small mb-0">
                        Let organisation members test chatbot routing with their own phone numbers before public rollout.
                    </p>
                </div>

                <div class="d-flex align-items-center align-self-start gap-3">
                    <div class="form-check form-switch mb-0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="allowInternalTesting"
                            name="allow_internal_testing"
                            value="true"
                            {{ filter_var($allowInternalTesting ?? 'false', FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' }}>
                        <label
                            class="form-check-label small fw-semibold d-inline-block"
                            for="allowInternalTesting"
                            style="min-width: 64px;">
                            {{ filter_var($allowInternalTesting ?? 'false', FILTER_VALIDATE_BOOLEAN) ? 'Enabled' : 'Disabled' }}
                        </label>
                    </div>
                </div>

                <div
                    id="gautamsBotConfigUnsavedNotice"
                    class="alert alert-warning py-2 px-3 mb-0 small d-none"
                    role="alert">
                    You have unsaved changes. Click Save before leaving this page.
                </div>

                <div>
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        Save
                    </button>
                </div>
            </div>
        </form>
    </div>

    @include('integration::components.inc-with-props.json-config-editor', [
        'configData' => $chatbot_vector,
        // 'saveUrl' => route('#'),
        'title' => 'Chatbot Vector',
        'method' => 'POST'
    ])
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const configForm = document.getElementById('gautamsBotConfigForm');
        const toggles = [
            document.getElementById('humanHandoverEnabled'),
            document.getElementById('allowInternalTesting'),
        ].filter(Boolean);

        if (!configForm || toggles.length === 0) {
            return;
        }

        const unsavedNotice = document.getElementById('gautamsBotConfigUnsavedNotice');
        const initialState = new Map(
            toggles.map((toggle) => [toggle.id, toggle.checked])
        );
        let isDirty = false;

        const syncLabel = (toggle) => {
            const label = document.querySelector(`label[for="${toggle.id}"]`);
            if (label) {
                label.textContent = toggle.checked ? 'Enabled' : 'Disabled';
            }
        };

        const syncDirtyState = () => {
            isDirty = toggles.some((toggle) => toggle.checked !== initialState.get(toggle.id));

            if (unsavedNotice) {
                unsavedNotice.classList.toggle('d-none', !isDirty);
            }
        };

        toggles.forEach((toggle) => syncLabel(toggle));
        syncDirtyState();

        toggles.forEach((toggle) => {
            toggle.addEventListener('change', function () {
                syncLabel(toggle);
                syncDirtyState();
            });
        });

        configForm.addEventListener('submit', function () {
            isDirty = false;
            if (unsavedNotice) {
                unsavedNotice.classList.add('d-none');
            }
        });

        window.addEventListener('beforeunload', function (event) {
            if (!isDirty) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        });
    });
</script>
@endpush
