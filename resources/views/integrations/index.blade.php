@extends('userinterface::layouts.app')

@section('content')
<div>
    <div class="mb-3">
        <h5 class="fs-6 text-muted">
            Integrations
        </h5>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-2">
        @foreach ($supportedIntegrations as $application)

            @php
                $integration = $integrations
                    ->firstWhere('supported_integration_id', $application->id);

                $isActive = (bool) $integration;
            @endphp

            <div class="col d-flex">
                <div class="shadow-sm border border-slate-300 rounded p-2 w-100 d-flex flex-column">

                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div>
                                <h5 class="fs-6 mb-0">
                                    {{ $application->name }}
                                </h5>
                                <small class="text-muted">
                                    Status Summary
                                </small>
                            </div>

                            <span class="badge ms-2 badge-{{ $isActive ? 'active' : 'draft' }}">
                                {{ $isActive ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <div class="d-flex flex-column align-items-end justify-content-center">
                            @if ($isActive)
                                <a
                                    href="{{ route('integrations.show', $integration->id) }}"
                                    class="btn btn-text text-primary btn-sm mb-1"
                                >
                                    <i class="fas fa-cog"></i>
                                    Configure
                                </a>
                            @endif

                            <div class="form-check form-switch
                                @cannot('edit-integrations') disabled @endcannot">
                                <input
                                    class="form-check-input toggle-switch"
                                    type="checkbox"
                                    id="integration-{{ $application->id }}"
                                    data-item-id="{{ $application->id }}"
                                    data-item-uid="{{ $application->uid }}"
                                    data-item-name="{{ $application->name }}"
                                    {{ $isActive ? 'checked' : '' }}
                                    @cannot('edit-integrations') disabled @endcannot
                                >
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="card-design-body">
                        <p class="text-muted mb-0">
                            {{ $application->getMeta('description') ?? 'No description available' }}
                        </p>
                    </div>

                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
