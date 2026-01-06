@extends('integration::layouts.app')

@section('content')
<div class="resizable-container p-2 bg-light d-flex" style="height: calc(100vh - 162px);">
    <!-- Left Panel - Tree Structure -->
    <div class="resizable-left" style="width: 50%; flex: 1; min-width: 200px; overflow-y: auto;">
        <div class="table-responsive">
            <table id="orgEntityTable" class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>{{ ucfirst($entityName) }} Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $entityCollection = $organisation->{strtolower($entityName)} ?? $availableEntity ?? collect();
                        $currentEntityId = $entity->id ?? null;
                    @endphp
                    @foreach($entityCollection as $orgEntity)
                    <tr class="{{ $orgEntity->id === $currentEntityId ? 'table-active' : '' }}">
                        <td>
                            @if(method_exists($orgEntity, 'uid'))
                                {{$orgEntity->name}}
                            @else
                                {{ $orgEntity->name ?? $orgEntity->title ?? 'Unnamed' }}
                            @endif
                        </td>
                        <td>
                            {{$apiIds}}
                            
                            {{-- @if(method_exists($orgEntity, 'uid'))
                                <a href="{{ route('organisations.' . strtolower($entityName) . '.api.matched-entity-display', [$organisation->uid, $integrationUid, $apiIds, $entityName, $orgEntity->uid]) }}"
                                class="btn btn-sm {{ $orgEntity->id === $currentEntityId ? 'btn-outline-warning' : 'btn-outline-primary' }}">
                                    {{ $orgEntity->id === $currentEntityId ? 'Viewing' : 'Find Match' }}
                                </a>
                            @else
                                <a href="{{ route('organisations.' . strtolower($entityName) . '.api.matched-entity-display', [$organisation->uid, $integrationUid, $apiIds, $entityName, $orgEntity->id]) }}"
                                class="btn btn-sm {{ $orgEntity->id === $currentEntityId ? 'btn-outline-warning' : 'btn-outline-primary' }}">
                                    {{ $orgEntity->id === $currentEntityId ? 'Viewing' : 'Find Match' }}
                                </a>
                            @endif --}}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Panel - Details View -->
    <div class="resizable-right p-3" style="flex: 1; min-width: 200px; overflow-y: auto;">
        <h5 class="fs-6 text-muted mb-3">Matching Results for: {{ $entity->name ?? $entity->title ?? 'Unnamed Entity' }}</h5>
        
        <div class="mb-3">
            <div>
                <h5 class="fs-6 text-muted">{{ ucfirst($entityName) }} Details</h5>
            </div>
            <div>
                <p><strong>Name:</strong> {{ $entity->name ?? $entity->title ?? 'N/A' }}</p>
                <p><strong>Email:</strong> {{ $entity->email ?? 'N/A' }}</p>
                <p><strong>Phone:</strong> {{ $entity->phone ?? 'N/A' }}</p>
                <!-- Display additional fields if available -->
                @if(method_exists($entity, 'meta') && $entity->meta()->get('pan_number'))
                    <p><strong>PAN Number:</strong> {{ $entity->meta()->get('pan_number') }}</p>
                @elseif(isset($entity->pan_number))
                    <p><strong>PAN Number:</strong> {{ $entity->pan_number }}</p>
                @endif
            </div>
        </div>
        
        <!-- Debug information -->
        <div class="mb-3 border-info">
            <div class="text-info">
                <h5 class="fs-6">Integration Details</h5>
            </div>
        </div>
        
        @if(count($exactMatches) > 0)
        <div class="mb-3 border-success">
            <div>
                <h5 class="fs-6 text-success">Exact Matches ({{ count($exactMatches) }})</h5>
            </div>
            <div>
                @foreach($exactMatches as $match)
                <div class="mb-2 p-2 border rounded">
                    <div class="d-flex align-items-start justify-content-start gap-2">
                        <!-- Display ALL extracted fields dynamically -->
                        <div>
                            @foreach($match['extracted_fields'] as $fieldName => $fieldValue)
                                @if(!empty($fieldValue) || $fieldValue === 0)
                                    <p><strong>{{ ucfirst(str_replace('_', ' ', $fieldName)) }}:</strong> 
                                        {{ is_array($fieldValue) ? json_encode($fieldValue) : $fieldValue }}
                                    </p>
                                @endif
                            @endforeach
                        </div>
                            @if ($match['extracted_fields']['id'])
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" 
                                    data-bs-target="#deleteExtIntegrationDataConfirmationModal-{{ $match['extracted_fields']['id'] }}">
                                    <i class="fas fa-fw fa-trash"></i>
                                </button>

                                {{-- Include modal here so $match is available --}}
                                {{-- @include('components.inc-confirmation-modal', [
                                    'modalId' => 'deleteExtIntegrationDataConfirmationModal-'.$match['extracted_fields']['id'],
                                    'title' => 'Confirm Deletion',
                                    'message' => 'Are you sure you want to delete <strong>Data From Zoho Books</strong>? If deleted it cannot be restored.',
                                    'action' => route('organisations.' . strtolower($entityName) . '.api.delete-entity-by-api', [
                                        $organisation->uid,
                                        $integrationUid,
                                        $entityName,
                                        $match['extracted_fields']['id']
                                    ]),
                                    'method' => 'DELETE',
                                    'submitButtonLabel' => 'Delete',
                                    'submitButtonClass' => 'btn-outline-danger',
                                ]) --}}
                            @endif
                    </div>
                    <p><strong>Match Score:</strong> {{ $match['match_score'] }}%</p>
                    @php
                        $savedIntegrationId = null;
                        $matchedEntity = null;

                        // Check if entity has meta method
                        if (method_exists($entity, 'meta')) {
                            $savedIntegrationId = $entity->meta()->get('ext_integration_id');
                        } elseif (isset($entity->ext_integration_id)) {
                            $savedIntegrationId = $entity->ext_integration_id;
                        }

                        if ($savedIntegrationId && $savedIntegrationId == $match['ext_integration']->id) {
                            // Dynamically get the entity model
                            $entityModel = '\\App\\Models\\' . ucfirst(Str::singular($entityName));
                            if (class_exists($entityModel)) {
                                $matchedEntity = $entityModel::find($entity->id);
                            }
                        }
                    @endphp

                    @if($matchedEntity)
                    <p>
                        <span class="text-success">
                            Matching Confirmed with {{ $matchedEntity->name ?? $matchedEntity->title ?? 'Entity' }}
                        </span>
                        {{-- @if(method_exists($entity, 'uid'))
                            <form action="{{ route('organisations.' . strtolower($entityName) . '.api.pull-data', [$organisation->uid, $entityName, $matchedEntity->uid, $match['ext_integration']->ext_id]) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">Pull Data</button>
                            </form>
                        @else
                            <form action="{{ route('organisations.' . strtolower($entityName) . '.api.pull-data', [$organisation->uid, $entityName, $matchedEntity->id, $match['ext_integration']->ext_id]) }}" method="POST" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">Pull Data</button>
                            </form>
                        @endif --}}
                    </p>
                    @else
                        {{-- @if(method_exists($entity, 'uid'))
                            <a href="{{ route('organisations.' . strtolower($entityName) . '.api.confirm-match', [
                                    $organisation->uid,
                                    $entityName,
                                    $entity->uid,
                                    $match['ext_integration']->ext_id
                                ]) }}"
                            class="btn btn-sm btn-outline-success">
                                Confirm Match
                            </a>
                        @else
                            <a href="{{ route('organisations.' . strtolower($entityName) . '.api.confirm-match', [
                                    $organisation->uid,
                                    $entityName,
                                    $entity->id,
                                    $match['ext_integration']->ext_id
                                ]) }}"
                            class="btn btn-sm btn-outline-success">
                                Confirm Match
                            </a>
                        @endif --}}
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif
        
        @if(count($partialMatches) > 0)
        <div class="mb-3 border-warning">
            <div>
                <h5 class="fs-6 text-warning">Partial Matches ({{ count($partialMatches) }})</h5>
            </div>
            <div>
                @foreach($partialMatches as $match)
                <div class="mb-2 p-2 border rounded">
                    <div class="d-flex align-items-start justify-content-start gap-2">
                        <div>
                            <!-- Display ALL extracted fields dynamically -->
                            @foreach($match['extracted_fields'] as $fieldName => $fieldValue)
                                @if(!empty($fieldValue) || $fieldValue === 0)
                                    <p><strong>{{ ucfirst(str_replace('_', ' ', $fieldName)) }}:</strong> 
                                        {{ is_array($fieldValue) ? json_encode($fieldValue) : $fieldValue }}
                                    </p>
                                @endif
                            @endforeach
                        </div>
                        @if ($match['extracted_fields']['id'])
                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" 
                                data-bs-target="#deleteExtIntegrationDataConfirmationModal-{{ $match['extracted_fields']['id'] }}">
                                <i class="fas fa-fw fa-trash"></i>
                            </button>

                            {{-- Include modal here so $match is available --}}
                            {{-- @include('components.inc-confirmation-modal', [
                                'modalId' => 'deleteExtIntegrationDataConfirmationModal-'.$match['extracted_fields']['id'],
                                'title' => 'Confirm Deletion',
                                'message' => 'Are you sure you want to delete <strong>Data From Zoho Books</strong>? If deleted it cannot be restored.',
                                'action' => route('organisations.' . strtolower($entityName) . '.api.delete-entity-by-api', [
                                    $organisation->uid,
                                    $integrationUid,
                                    $entityName,
                                    $match['extracted_fields']['id']
                                ]),
                                'method' => 'DELETE',
                                'submitButtonLabel' => 'Delete',
                                'submitButtonClass' => 'btn-outline-danger',
                            ]) --}}
                        @endif
                    </div>
                    <p><strong>Match Score:</strong> {{ $match['match_score'] }}%</p>
                    <button class="btn btn-sm btn-outline-warning">Review Match</button>
                </div>
                @endforeach
            </div>
        </div>
        @endif
        
        @if(count($exactMatches) === 0 && count($partialMatches) === 0)
        <div class="alert alert-warning">
            <h5 class="fs-6 text-muted">No matches found</h5>
            <p>No matches found for this {{ strtolower($entityName) }} in the external integration data.</p>
            <button class="btn btn-sm btn-outline-primary"
                data-bs-toggle="modal"
                data-bs-target="#pushToZoho">
                <i class="fas fa-fw fa-upload me-2"></i> Push to Zoho
            </button>

            @if(count($externalIntegrations) === 0)
                <p><strong>No external integrations found for this organisation.</strong></p>
            @else
                <p>Possible configuration issues:</p>
                <ul>
                    <li>Check if the field paths in configuration match the actual data structure</li>
                    <li>Verify that the external data contains the expected fields</li>
                    <li>Consider using field overrides in your configuration if paths don't match</li>
                </ul>
            @endif
        </div>
        @endif
    </div>
</div>

<!-- Push to Zoho Modal -->
<div class="modal fade" id="pushToZoho" tabindex="-1" aria-labelledby="pushToZohoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      
      <div class="modal-header">
        <h5 class="modal-title fs-6" id="pushToZohoLabel">Push to Zoho</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <strong>Do you want to push {{ strtolower($entityName) }} data to Zoho Books?</strong><br>
        This process will take about 1 minute.<br>
        
        <!-- Contact type selection form -->
        {{-- @if(method_exists($entity, 'uid'))
            <form action="{{ route('organisations.' . strtolower($entityName) . '.api.create-entity-by-api', [$organisation->uid, $integrationUid, $pushApiId, $entityName, $entity->uid]) }}" method="POST">
        @else
            <form action="{{ route('organisations.' . strtolower($entityName) . '.api.create-entity-by-api', [$organisation->uid, $integrationUid, $pushApiId, $entityName, $entity->id]) }}" method="POST">
        @endif --}}
            @csrf
            <div class="form-group mt-3">
                <label>Select Type:</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="contact_type" id="contact_type_customer" value="customer" checked>
                    <label class="form-check-label" for="contact_type_customer">Customer</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="contact_type" id="contact_type_vendor" value="vendor">
                    <label class="form-check-label" for="contact_type_vendor">Vendor</label>
                </div>
            </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-outline-primary">Push to Zoho</button>
        </form>
      </div>

    </div>
  </div>
</div>

@endsection

@section('scripts')
<script>
    // Make the panels resizable
    $(document).ready(function() {
        $('.resizable-container').resizable({
            handles: 'e',
            resize: function(event, ui) {
                $('.resizable-left').width(ui.size.width);
            }
        });
        
        // Add click handler to highlight selected row
        $('#orgEntityTable tbody tr').click(function() {
            $('#orgEntityTable tbody tr').removeClass('table-active');
            $(this).addClass('table-active');
        });
    });
</script>
@endsection