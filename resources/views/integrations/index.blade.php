@extends('userinterface::layouts.app')

@section('content')
{{-- ===================== --}}
{{-- Connected Integrations --}}
{{-- ===================== --}}
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="fs-6 text-muted">Total {{ $integrations->count() }} Integration(s)</h5>

        <a href="#" class="btn btn-sm btn-outline-primary">
            <i class="fa-regular fa-fw fa-plus"></i>
            <span class="d-none d-md-inline-block ms-1">Integration</span>
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover" id="integrations-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($integrations as $integration)
                    <tr>
                        <td>
                            {{ $integration->supportedIntegration->name ?? '-' }}
                        </td>

                        <td>
                            {{ $integration->organisation->name
                                ?? $integration->user->name
                                ?? 'Personal' }}
                        </td>

                        <td>
                            <span class="badge badge-active">
                                Active
                            </span>
                        </td>

                        <td>
                            <a
                                href="{{ route('integrations.show', $integration->id) }}"
                                class="btn btn-sm btn-primary"
                            >
                                Configure
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ===================== --}}
{{-- Supported Integrations --}}
{{-- ===================== --}}
<div class="mb-3">
    <h5 class="fs-6 text-muted">
        Supported Integrations
    </h5>
</div>

<div class="row g-3">
    @foreach ($supportedIntegrations as $application)

        @php
            $icon = $application->getMeta('icon')
                ?? '<i class="fa-brands fa-whatsapp"></i>';
        @endphp

        <x-userinterface::card-item
            type="integration"
            :key="Str::slug($application->name)" {{-- woocommerce --}}
            :title="$application->name"
            :description="$application->getMeta('description') ?? 'No description available'"
            :icon="$icon"
        >
            <a href="#" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-plus me-1"></i> Integration
            </a>
        </x-userinterface::card-item>

    @endforeach
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('#integrations-table').DataTable({
            responsive: true
        });
    });
</script>
@endpush