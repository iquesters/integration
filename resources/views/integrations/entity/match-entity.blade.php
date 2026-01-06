@extends('integration::layouts.app')

@section('content')
    <div class="resizable-container p-2 bg-light d-flex" style="height: calc(100vh - 200px);">
    
    <!-- Left Panel - Entity List -->
    <div class="resizable-left" style="width: 50%; min-width: 200px;">
        <div class="table-responsive">
            <table id="entityTable" class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($availableEntity as $entity)
                        <tr>
                            <td>{{ $entity->name ?? 'N/A' }}</td>

                            <td>
                                {{-- @if(isset($organisation) && $organisation) --}}
                                <a href="{{ route('organisations.integration.api.matched-entity-display', [$organisation->uid, $integrationUid, $apiId, $entityName, $entity->id]) }}"
                                    class="btn btn-sm btn-outline-primary">
                                    Find Match
                                </a>
                                {{-- @endif --}}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center">No {{ $entityName }} found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Panel - Details / Info -->
    <div class="resizable-right" style="flex: 1; min-width: 200px;">
        <div class="p-3">
            <h5>{{ ucfirst($entityName) }} Matching</h5>
            <p class="text-muted">
                Select an item from the left panel to find matches in the external integration data.
            </p>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> This feature helps you match your {{ $entityName }} with data from external integrations.
            </div>
        </div>
    </div>
@endsection