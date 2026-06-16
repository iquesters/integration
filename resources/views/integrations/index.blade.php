@extends(app('app.layout'))

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make(['Integration']))
@section('meta-description', \Iquesters\Foundation\Helpers\MetaHelper::description('List of Integration'))

@php
    $tabs = [
        [
            'route' => 'integration.index',
            'params' => [],
            'icon' => 'fa-solid fa-fw fa-link',
            'label' => 'Integrations',
        ],
    ];
@endphp

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
                            <x-userinterface::status :status="$integration->status" />
                        </td>

                        <td>
                            {{ optional($integration->creator)->name ?? '-' }}
                            <br>
                            <small>{{ $integration->created_at->format('d M Y') }}</small>
                        </td>
                        <td>
                            {{ method_exists($integration, 'organisations') ? optional($integration->organisations->first())->name ?? '-' : '-' }}
                        </td>

                        <td>
                            <div class="d-flex align-items-center justify-content-center gap-2">
                                @if ($integration->status !== 'deleted')
                                    <a class="btn btn-sm btn-outline-dark" href="{{ route('integration.edit', $integration->uid) }}">
                                        <i class="fas fa-fw fa-edit"></i>
                                    </a>
                                    <form action="{{ route('integration.destroy', $integration->uid) }}" method="POST" onsubmit="return confirm('Are you sure?')">
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
@php
    $groupedSupportedIntegrations = $supportedIntegrations
        ->sortBy(function ($application) {
            return [
                $application->category ?: 'other',
                $application->name,
            ];
        })
        ->groupBy(function ($application) {
            return $application->category ?: 'other';
        });
@endphp

<div data-ui-group-filter>
    <div class="mb-3 d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <h5 class="fs-6 text-muted mb-0">
            Supported Integrations
        </h5>

        <div class="d-flex align-items-center gap-2 ms-auto">
            <label for="integration-category-filter" class="small text-muted mb-0">Category:</label>
            <select id="integration-category-filter" class="form-select form-select-sm" style="min-width: 220px;" data-ui-group-filter-select>
                <option value="all" selected>All</option>
                @foreach ($groupedSupportedIntegrations as $category => $applications)
                    <option value="{{ $category }}">{{ ucfirst(str_replace(['-', '_'], ' ', $category)) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @forelse ($groupedSupportedIntegrations as $category => $applications)
        <div class="mb-2" data-ui-group-filter-group="{{ $category }}">
            <div class="d-flex align-items-center mb-2">
                <h6 class="mb-0 text-muted fw-semibold d-flex align-items-center">
                    {{ ucfirst(str_replace(['-', '_'], ' ', $category)) }}
                    <x-userinterface::badge :text="(string) $applications->count()" class="text-primary-emphasis rounded-pill px-2 py-1 ms-2 border-0 shadow-sm" />
                </h6>
            </div>

            <div class="row g-2">
                @foreach ($applications as $application)
                    @php
                        $icon = $application->getMeta('icon')
                            ?? '<i class="fa-brands fa-whatsapp"></i>';
                    @endphp

                    @include('userinterface::components.card-item', [
                        'type'        => 'integration',
                        'key'         => Str::slug($application->name),
                        'title'       => $application->name,
                        'description' => $application->getMeta('description') ?? 'No description available',
                        'icon'        => $icon,
                        'application' => $application,
                    ])
                @endforeach
            </div>
        </div>
    @empty
        <div class="alert alert-light border text-muted mb-0">
            No supported integrations available.
        </div>
    @endforelse
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
