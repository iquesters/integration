@extends(app('app.layout'))

@section('page-title', \Iquesters\Foundation\Helpers\MetaHelper::make([($isEdit ? 'Edit' : 'Create'), 'Integration']))
@section('meta-description', \Iquesters\Foundation\Helpers\MetaHelper::description('Create/Edit of Integration'))

@section('content')
<div>

<h5 class="mb-2 fs-6 text-muted">
    {{ $isEdit ? 'Edit Integration' : 'Create Integration' }}
</h5>

<form method="POST"
      action="{{ $isEdit ? route('integration.update', $integration->uid) : route('integration.store') }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row g-3 mb-3">
        {{-- Integration Selection --}}
        <div class="col-md-4">
            <label class="form-label">Integration <span class="text-danger">*</span></label>
            @if(!empty($selectedIntegration) && !$isEdit)
                {{-- In create mode with pre-selected integration --}}
                <input type="text"
                       class="form-control"
                       value="{{ $selectedIntegration->name }}"
                       disabled>
                <input type="hidden" name="supported_integration_id" value="{{ $selectedIntegration->id }}">
            @else
                {{-- In edit mode or create without pre-selection --}}
                <select name="supported_integration_id" class="form-select" required>
                    <option value="">-- Select Integration --</option>
                    @foreach($supportedIntegrations as $app)
                        <option value="{{ $app->id }}"
                            {{ old('supported_integration_id', 
                                $isEdit ? $integration->supported_integration_id : '') == $app->id ? 'selected' : '' }}>
                            {{ $app->name }}
                        </option>
                    @endforeach
                </select>
            @endif
        </div>

        {{-- Integration Name --}}
        <div class="col-md-4">
            <label class="form-label">Integration Name <span class="text-danger">*</span></label>
            <input type="text"
                   name="name"
                   class="form-control"
                   value="{{ old('name', $integration->name ?? '') }}"
                   required>
        </div>

        {{-- Organisation (if user has organisations) --}}
        @if($organisations->count() > 0)
            <div class="col-md-4">
                <label class="form-label">Organisation</label>
                <select name="organisation_id" class="form-select">
                    <option value="">-- Select Organisation --</option>
                    @foreach($organisations as $org)
                        <option value="{{ $org->id }}"
                            {{ old(
                                'organisation_id',
                                optional(optional($integration)->organisations)->first()?->id
                            ) == $org->id ? 'selected' : '' }}
                            >
                            {{ $org->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <div class="mt-4 d-flex justify-content-end gap-2">
        <a href="{{ route('integration.index') }}"
           class="btn btn-sm btn-outline-dark">
            Cancel
        </a>

        <button type="submit" class="btn btn-sm btn-outline-primary">
            {{ $isEdit ? 'Update Integration' : 'Create Integration' }}
        </button>
    </div>

</form>
</div>
@endsection