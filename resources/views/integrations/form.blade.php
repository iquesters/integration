@extends(app('app.layout'))

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make([($isEdit ? 'Edit' : 'Create'), 'Integration']))
@section('meta-description', \Iquesters\Foundation\Helpers\MetaHelper::description('Create/Edit of Integration'))

@section('content')
<div>

<h5 class="mb-2 fs-6 text-muted">
    {{ $isEdit ? 'Edit Integration' : 'Create Integration' }}
</h5>

{{-- Progress --}}
<div class="mb-4">
    <div class="d-flex align-items-center">
        {{-- Step 1 --}}
        <div class="d-flex align-items-center {{ $step == 1 ? 'text-primary' : 'text-success' }}">
            <div class="rounded-circle border {{ $step == 1 ? 'border-primary' : 'border-success' }} d-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:14px;font-weight:500;">
                {{ $step == 2 ? '✓' : '1' }}
            </div>
            <span class="ms-2 small">Basic Info</span>
        </div>

        <div class="flex-grow-1 mx-3" style="height:2px;background:{{ $step == 2 ? '#198754' : '#dee2e6' }}"></div>

        {{-- Step 2 --}}
        <div class="d-flex align-items-center {{ $step == 2 ? 'text-primary' : 'text-muted' }}">
            <div class="rounded-circle border border-secondary d-flex align-items-center justify-content-center"
                style="width:32px;height:32px">2</div>
            <span class="ms-2 small">Credentials</span>
        </div>
    </div>
</div>

<form method="POST"
      action="{{ $step == 1 
                 ? ($isEdit 
                     ? route('integration.update-step1', $integration->uid) 
                     : route('integration.store-step1')) 
                 : ($isEdit 
                     ? route('integration.update', $integration->uid) 
                     : route('integration.store')) }}">
    @csrf
    @if($step == 2 && $isEdit)
        @method('PUT')
    @endif

    {{-- STEP 1 --}}
    @if($step == 1)
    <div class="row g-3 mb-3">

        <div class="col-md-4">
            <label class="form-label">Integration <span class="text-danger">*</span></label>
            @if(!empty($selectedIntegration))
                <input type="text"
                       class="form-control"
                       value="{{ $selectedIntegration->name }}"
                       disabled>
                <input type="hidden" name="supported_integration_id" value="{{ $selectedIntegration->id }}">
            @else
                <select name="supported_integration_id" class="form-select" required>
                    <option value="">-- Select Integration --</option>
                    @foreach($supportedIntegrations as $app)
                        <option value="{{ $app->id }}"
                            {{ old('supported_integration_id', $sessionData['supported_integration_id'] ?? '') == $app->id ? 'selected' : '' }}>
                            {{ $app->name }}
                        </option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="col-md-4">
            <label class="form-label">Integration Name <span class="text-danger">*</span></label>
            <input type="text"
                   name="name"
                   class="form-control"
                   value="{{ old('name', $sessionData['name'] ?? ($integration->name ?? '')) }}"
                   required>
        </div>

        @if($organisations->count() > 0)
            <div class="col-md-4">
                <label class="form-label">Organisation</label>
                <select name="organisation_id" class="form-select">
                    <option value="">-- Select Organisation --</option>
                    @foreach($organisations as $org)
                        <option value="{{ $org->id }}"
                            {{ old(
                                'organisation_id',
                                $sessionData['organisation_id']
                                    ?? optional(optional($integration)->organisations)->first()?->id
                            ) == $org->id ? 'selected' : '' }}
                            >
                            {{ $org->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

    </div>
    @endif

    {{-- STEP 2 --}}
    @if($step == 2)
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">API URL <span class="text-danger">*</span></label>
            <input type="url"
                   name="meta[url]"
                   class="form-control"
                   placeholder="https://api.example.com"
                   value="{{ old('meta.url', optional($integration)->getMeta('url') ?? '') }}"
                   required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Client Key <span class="text-danger">*</span></label>
            <input type="text"
                   name="meta[client_key]"
                   class="form-control"
                   value="{{ old('meta.client_key', optional($integration)->getMeta('client_key') ?? '') }}"
                   required>
        </div>

        <div class="col-12">
            <label class="form-label">Client Token <span class="text-danger">*</span></label>
            <textarea name="meta[client_token]"
                      class="form-control"
                      rows="3"
                      required>{{ old('meta.client_token', optional($integration)->getMeta('client_token') ?? '') }}</textarea>
        </div>
    </div>
    @endif

    <div class="mt-4 d-flex justify-content-end gap-2">
        <a href="{{ route('integration.index') }}"
           class="btn btn-sm btn-outline-dark">
            Cancel
        </a>

        <button type="submit" class="btn btn-sm btn-outline-primary">
            {{ $step == 1 ? 'Next' : ($isEdit ? 'Update Integration' : 'Create Integration') }}
        </button>
    </div>

</form>
</div>
@endsection