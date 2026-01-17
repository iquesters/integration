@extends(app('app.layout'))

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make([
    'Api Conf',
    ($integration->name ?? 'Integration'),
    'Woocommerce',
    'Integration'
]))

@section('meta-description', \Iquesters\Foundation\Helpers\MetaHelper::description(
    'Api Configure page of Integration'
))

@php
    $tabs = [
        [
            'route' => 'integration.show',
            'params' => ['integrationUid' => $integration->uid],
            'icon' => 'far fa-fw fa-list-alt',
            'label' => 'Overview',
        ],
        [
            'route' => 'integration.configure',
            'params' => ['integrationUid' => $integration->uid],
            'icon' => 'fas fa-fw fa-sliders-h',
            'label' => 'Configure',
        ],
        [
            'route' => 'integration.apiconf',
            'params' => ['integrationUid' => $integration->uid],
            'icon' => 'fas fa-fw fa-screwdriver-wrench',
            'label' => 'Api Conf',
        ],
        [
            'route' => 'integration.syncdata',
            'params' => ['integrationUid' => $integration->uid],
            'icon' => 'fas fa-fw fa-rotate',
            'label' => 'Sync Data',
        ],
    ];

    $apiMetaKey = $integration->small_name . '_api_id';
@endphp

@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fs-6 text-muted">
        {{ $integration->name }} Integration – API Configuration
    </h5>
</div>

<!-- Selected APIs -->
<div class="card mb-3">
    <div class="card-body">

        <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="text-muted mb-0">Selected APIs</h6>

            <button
                type="button"
                class="btn btn-sm btn-outline-primary"
                data-bs-toggle="modal"
                data-bs-target="#manageApisModal"
            >
                Manage API
            </button>
        </div>

        <ul class="list-group list-group-flush">
            @forelse($selectedApis as $api)
                <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                    <span>{{ $api->meta_key }}</span>

                    <a href="{{ route('integration.apiconf.configure', ['integrationUid' => $integration->uid, 'apiId' => $api->id]) }}" class="btn btn-sm text-primary">
                        <i class="fas fa-fw fa-cog"></i> Configure
                    </a>
                </li>
            @empty
                <li class="list-group-item text-muted">
                    No APIs selected
                </li>
            @endforelse
        </ul>

    </div>
</div>

<!-- Manage APIs Modal -->
<div
    class="modal fade"
    id="manageApisModal"
    tabindex="-1"
    aria-labelledby="manageApisModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">

            <form
                method="POST"
                action="{{ route('integration.apiconf.save', ['integrationUid' => $integration->uid]) }}"
            >
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="manageApisModalLabel">
                        Manage {{ $integration->name }} APIs
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    @if($apis->isEmpty())
                        <p class="text-muted mb-0">
                            No active APIs available for this integration provider.
                        </p>
                    @else
                        <div class="list-group">
                            @foreach($apis as $api)
                                <label class="list-group-item d-flex align-items-center">
                                    <input
                                        type="checkbox"
                                        class="form-check-input me-2"
                                        name="selected_metas[]"
                                        value="{{ $api->id }}"
                                        {{ in_array($api->id, $selectedApiIds) ? 'checked' : '' }}
                                    >
                                    <span>{{ $api->meta_key }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif

                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-dark"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-sm btn-outline-primary"
                    >
                        Save APIs
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

@endsection