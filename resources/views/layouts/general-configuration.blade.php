@extends('integration::layouts.app')

@section('content')
    @include('integration::components.inc-with-props.layout-tabs', [
        'tabs' => [
            [
                'id' => 'overview',
                'route' => 'organisations.integration.show',
                'params' => ['organisationUid' => $organisation->uid, 'integrationUid' => $application->uid],
                'icon' => 'far fa-list-alt',
                'label' => 'Overview',
                // 'permission' => 'view-roles'
            ],
            [
                'id' => 'data',
                'route' => 'organisations.integration.data',
                'params' => ['organisationUid' => $organisation->uid, 'integrationUid' => $application->uid],
                'icon' => 'fas fa-layer-group',
                'label' => 'Data',
                // 'permission' => 'view-roles'
            ],
        ],
        'baseRoute' => 'organisations.integration.show',
        'baseRouteParams' => ['organisationUid' => $organisation->uid, 'integrationUid' => $application->uid],
        'tabId' => 'roleTabs',
        'sticky' => true
    ])
@yield('general-configuration-content')
@endsection