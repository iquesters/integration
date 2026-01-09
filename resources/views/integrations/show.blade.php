@extends('userinterface::layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="d-flex align-items-center justify-content-start gap-2">
            <h5 class="fs-6 text-muted mb-0">
                {{ $integration->name }}
                {!! $integration->supportedInt?->getMeta('icon') !!}
            </h5>
            <span class="badge badge-{{ strtolower($integration->status) }}">
                {{ ucfirst($integration->status) }}
            </span>
        </div>

        <div class="d-flex align-items-center justify-content-center gap-2">
            @if ($integration->status !== 'deleted')   
                <a class="btn btn-sm btn-outline-dark" href="{{ route('integration.edit', $integration->uid) }}">
                    <i class="fas fa-fw fa-edit"></i>
                    <span class="d-none d-md-inline-block ms-1">Edit</span>
                </a>
                <form action="{{ route('integration.destroy', $integration->uid) }}" 
                    method="POST" 
                    onsubmit="return confirm('Are you sure?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-fw fa-trash"></i>
                        <span class="d-none d-md-inline-block ms-1">Delete</span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    @php
        use Illuminate\Support\Str;
    @endphp

    @if($integration->metas->isNotEmpty())
        <div class="mb-3">

            @foreach($integration->metas as $meta)
                @php
                    $isUrl    = $meta->meta_key === 'url';
                    $isSecret = Str::contains($meta->meta_key, ['token', 'key']);

                    $displayValue = $isSecret
                        ? Str::mask($meta->meta_value, '*', 0, max(strlen($meta->meta_value) - 4, 0))
                        : $meta->meta_value;
                @endphp

                <div class="d-flex align-items-center justify-content-start gap-2">

                    {{-- Key --}}
                    <div class="text-muted text-nowrap">
                        {{ Str::headline($meta->meta_key) }} :
                    </div>

                    {{-- Value --}}
                    <div class=" text-break"
                        id="meta-{{ $meta->id }}">
                        <code>{{ $displayValue }}</code>
                    </div>

                    {{-- Copy ONLY for URL --}}
                    @if($isUrl)
                        <i class="fas fa-copy text-muted copy-icon ms-2"
                        onclick="copyText('meta-{{ $meta->id }}', this)"
                        title="Copy URL"></i>
                    @endif

                </div>
            @endforeach

        </div>
    @endif
    
    <div>
        Channel section
    </div>
@endsection