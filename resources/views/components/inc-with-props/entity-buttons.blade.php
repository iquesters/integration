@php
    // $buttons should be an array of button configurations
    $buttons = $buttons ?? [];
    $class = $class ?? 'd-flex flex-wrap align-items-center justify-content-end gap-2';
    $globalDisabled = $globalDisabled ?? false; // Global disable flag
@endphp

<div class="{{ $class }}">
    @foreach($buttons as $button)
        @php
            // Set defaults for each button
            $type = $button['type'] ?? 'button';
            $tag = in_array($type, ['button', 'submit']) ? 'button' : $type;
            $color = $button['color'] ?? 'dark';
            $size = $button['size'] ?? 'sm';
            $icon = $button['icon'] ?? null;
            $text = $button['text'] ?? null;
            $permission = $button['permission'] ?? null;
            $disabled = $button['disabled'] ?? false;
            $modal = $button['modal'] ?? null;
            $confirm = $button['confirm'] ?? null;
            $href = $button['href'] ?? null;
            $action = $button['action'] ?? null;
            $method = $button['method'] ?? 'POST';
            $hiddenFields = $button['hiddenFields'] ?? [];
            $inlineText = $button['inlineText'] ?? false;
            $additionalClasses = $button['additionalClasses'] ?? '';
            $attributes = $button['attributes'] ?? [];
            $id = $button['id'] ?? null;
            $formAttributes = $button['formAttributes'] ?? [];
            
            // Check if button should be disabled (individual disabled OR global disabled)
            $isDisabled = $disabled || $globalDisabled;
            
            // Build base class string (without permission-based classes)
            $btnClass = "btn btn-{$size} btn-outline-{$color} rounded {$additionalClasses}";
            
            // Handle form wrapper
            $isForm = $type === 'form';
        @endphp

        @if($isForm)
        <form action="{{ $action }}" method="POST" class="d-inline"
            @if(isset($button['formAttributes']))
            @foreach($button['formAttributes'] as $attr => $val) {{ $attr }}="{{ $val }}" @endforeach
            @endif>
            @csrf
            @if(!in_array(strtoupper($method), ['GET', 'POST']))
                @method($method)
            @endif
            @foreach($hiddenFields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            
            <button type="submit"
                @if($id) id="{{ $id }}" @endif
                class="{{ trim($btnClass) }} @if($permission) @cannot($permission) disabled @endcannot @endif @if($isDisabled) disabled @endif"
                @if($permission) @cannot($permission) disabled @endcannot @endif
                @if($isDisabled) disabled @endif
                @if($confirm && !$isDisabled) onclick="return confirm('{{ $confirm }}')" @endif
                @foreach($attributes as $attr => $val) {{ $attr }}="{{ $val }}" @endforeach
            >
                @if($icon)<i class="{{ $icon }}"></i>@endif
                @if($text)<span class="{{ $inlineText ? '' : 'd-none d-md-inline-block' }} {{ $icon ? 'ms-1' : '' }}">{{ $text }}</span>@endif
            </button>
        </form>
        @else
        <{{ $tag }}
            @if($tag === 'a' && !$isDisabled) href="{{ $href }}" @endif
            @if($tag === 'button') type="{{ $type }}" @endif
            @if($id) id="{{ $id }}" @endif
            class="{{ trim($btnClass) }} @if($permission) @cannot($permission) disabled @endcannot @endif @if($isDisabled) disabled @endif"
            @if($permission) @cannot($permission) disabled @endcannot @endif
            @if($isDisabled) disabled @endif
            @if($modal && !$isDisabled) data-bs-toggle="modal" data-bs-target="#{{ $modal }}" @endif
            @if($confirm && !$isDisabled) onclick="return confirm('{{ $confirm }}')" @endif
            @foreach($attributes as $attr => $val) {{ $attr }}="{{ $val }}" @endforeach
        >
            @if($icon)<i class="{{ $icon }}"></i>@endif
            @if($text)<span class="{{ $inlineText ? '' : 'd-none d-md-inline-block' }} {{ $icon ? 'ms-1' : '' }}">{{ $text }}</span>@endif
        </{{ $tag }}>
        @endif
    @endforeach
</div>