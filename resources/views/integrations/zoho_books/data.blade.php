@extends('integration::layouts.general-configuration')

@section('general-configuration-content')

@if ($hasConfMeta)
    <button class="btn btn-sm btn-outline-primary"
        data-bs-toggle="modal"
        data-bs-target="#confirmSyncModal">
        <i class="fas fa-fw fa-sync-alt me-2"></i> Sync {{ ucfirst($hasConfMeta) }}
    </button>
    @php
        // Convert single API ID to a comma-separated list if needed
        $apiIdsToPass = is_array($apiIds) ? implode(',', $apiIds) : $apiIds;
    @endphp
    <a class="btn btn-sm btn-outline-info" href="{{ route('organisations.integration.api.entity-list', [$organisation->uid, $zohoIntegrationUid, $apiIdsToPass, $entityName])}}">{{ ucfirst($hasConfMeta) }} List</a>
@endif

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmSyncModal" tabindex="-1" aria-labelledby="confirmSyncLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title fs-6" id="confirmSyncLabel">Confirm Sync</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <strong>Do you want to sync person data from Zoho Books?</strong>
        This process will take about 2–3 minutes.
        Please note: any existing data will be deleted and replaced with the latest data from the API.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Cancel</button>

        <!-- Form submits directly to controller -->
        @if ($hasConfMeta && $entityName)
            <form action="{{ route('organisations.integration.api.api-call', [
                $organisation->uid,
                $zohoIntegrationUid,
                $apiMetaId,
                $entityName
            ]) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-primary">Confirm</button>
            </form>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection