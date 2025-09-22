@extends('integration::layouts.app')

@section('content')
<div>
    <div class="mb-3">
        <h5 class="fs-6 text-muted">
            Integrations
        </h5>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-2">
        @foreach ($applicationNames as $application)
            @php
                $isActive = $organisation->hasActiveIntegration($application->id);
            @endphp
            <div class="col d-flex">
                <div class="shadow-sm border border-slate-300 rounded p-2 w-100 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center justify-content-center">
                            <div>
                                <h5 class="fs-6 mb-0">{{ $application->name ?? '' }}</h5>
                                <small><small class="text-muted">Status Summary</small></small>
                            </div>
                            
                            <span class="badge ms-2 badge-{{ $isActive ? 'active' : 'draft' }}">
                                {{ $isActive ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="d-flex flex-column align-items-end justify-content-center">
                            @if ($isActive)
                                {{-- <a href="{{ route('organisations.integration.show', ['organisationUid' => $organisation->uid, 'integrationUid' => $application->uid]) }}" class="btn btn-text text-primary btn-sm mb-1">
                                    <i class="fas fa-cog"></i>
                                    Configure
                                </a> --}}
                            @endif
                            <div class="form-check form-switch @cannot('edit-organisations-integrations') disabled @endcannot" @cannot('edit-organisations-integrations') disabled @endcannot>
                                <input class="form-check-input toggle-switch" 
                                    type="checkbox" 
                                    id="integration-{{ $application->id }}" 
                                    data-item-id="{{ $application->id }}"
                                    data-item-uid="{{ $application->uid }}"
                                    data-item-name="{{ $application->name ?? 'Integration' }}"
                                    {{ $isActive ? 'checked' : '' }}
                                    @cannot('edit-organisations-integrations') disabled @endcannot>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="card-design-body">
                        <div class="col d-flex align-items-center">
                            <p class="text-muted mb-0">
                                {{ $application->getMeta('description') ?? 'No description available' }}
                            </p>
                        </div>
                    </div>
                    
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
    @include('integration::components.inc-with-props.toggle-switch', [
        'config' => [
            'modalId' => 'integrationToggleModal',
            'formId' => 'integrationToggleForm',
            'routeTemplate' => route('organisations.integration.toggle', ['organisationUid' => $organisation->uid, 'integrationId' => '__ITEM_UID__']),
            'permission' => 'edit-organisations-integrations',
            'title' => 'Integration Action',
            'message' => 'Are you sure you want to proceed with this action? This will affect the integration\'s availability for your organisation.'
        ]
    ])
@endpush