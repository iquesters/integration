@extends('userinterface::layouts.app')

@section('content')
{{-- ===================== --}}
{{-- Connected Integrations --}}
{{-- ===================== --}}
<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="fs-6 text-muted">Total {{ $integrations->count() }} Integration(s)</h5>

        <a href="{{ route('integration.create') }}" class="btn btn-sm btn-outline-primary">
            <i class="fa-regular fa-fw fa-plus"></i>
            <span class="d-none d-md-inline-block ms-1">Integration</span>
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-hover" id="integrations-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Organisation</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($integrations as $integration)
                    <tr>
                        <td>
                            <a href="{{ route('integration.show', $integration->uid) }}" class="text-decoration-none">
                                {{ $integration->name ?? '-' }}
                            </a>
                            {!! $integration->supportedInt?->getMeta('icon') !!}
                            <br>
                            <small class="text-muted">{{ $integration->getMeta('url') ?? '' }}</small>
                        </td>

                        <td>
                            <span class="badge badge-{{ strtolower($integration->status) }}">
                                {{ ucfirst($integration->status) }}
                            </span>
                        </td>

                        <td>
                            {{
                                optional($integration->creator)->name ?? '-'
                            }}
                            <br>
                            <small>
                                {{ $integration->created_at->format('d M Y') }}
                            </small>
                        </td>
                        <td>
                            {{
                                method_exists($integration, 'organisations')
                                    ? optional($integration->organisations->first())->name ?? '-'
                                    : '-'
                            }}
                        </td>

                        <td>
                            <div class="d-flex align-items-center justify-content-center gap-2">
                                @if ($integration->status !== 'deleted')   
                                    <a class="btn btn-sm btn-outline-dark" href="{{ route('integration.edit', $integration->uid) }}">
                                        <i class="fas fa-fw fa-edit"></i>
                                    </a>
                                    <form action="{{ route('integration.destroy', $integration->uid) }}" 
                                        method="POST" 
                                        onsubmit="return confirm('Are you sure?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-fw fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<hr class="my-4">

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

        @include('userinterface::inc.card-item', [
            'type'        => 'integration',
            'key'         => Str::slug($application->name),
            'title'       => $application->name,
            'description' => $application->getMeta('description') ?? 'No description available',
            'icon'        => $icon,
            'application' => $application,
        ])

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