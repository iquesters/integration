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
        <span class="badge badge-{{ strtolower($integration->status) }}">
            {{ ucfirst($integration->status) }}
        </span>
    </div>

    <table class="table table-bordered table-striped align-middle">
        <thead class="table-light">
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

@endsection