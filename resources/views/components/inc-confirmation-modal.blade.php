@php
    // Default values
    $modalId = $modalId ?? 'confirmationModal';
    $formId = $formId ?? 'confirmationForm';
    $title = $title ?? 'Confirm Action';
    $message = $message ?? 'Are you sure you want to perform this action?';
    $action = $action ?? '';
    $method = $method ?? 'POST';
    $submitButtonLabel = $submitButtonLabel ?? 'Confirm';
    $submitButtonClass = $submitButtonClass ?? 'btn-outline-primary';
    $submitButtonDisabled = $submitButtonDisabled ?? false;
    $hiddenInputs = $hiddenInputs ?? [];
    $extraFields = $extraFields ?? [];
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <form id="{{ $formId }}" action="{{ $action }}" method="POST">
        @csrf
        @method($method)
        
        @foreach($hiddenInputs as $input)
            <input type="hidden" 
                   name="{{ $input['name'] }}" 
                   value="{{ $input['value'] }}"
                   @isset($input['id']) id="{{ $input['id'] }}" @endisset>
        @endforeach

        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fs-6" id="{{ $modalId }}Label">{{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {!! $message !!}
                    
                    {{-- Extra fields section --}}
                    @foreach($extraFields as $field)
                        <div class="mb-3 my-2">
                            @if(isset($field['label']))
                                <label for="{{ $field['id'] ?? $field['name'] }}" class="form-label text-muted">{{ $field['label'] }}</label>
                            @endif
                            
                            @if($field['type'] === 'textarea')
                                <textarea 
                                    class="form-control" 
                                    id="{{ $field['id'] ?? $field['name'] }}" 
                                    name="{{ $field['name'] }}" 
                                    placeholder="{{ $field['placeholder'] ?? '' }}"
                                    @if(isset($field['required'])) required @endif
                                    rows="{{ $field['rows'] ?? 3 }}"
                                >{{ $field['value'] ?? '' }}</textarea>
                            @else
                                <input 
                                    type="{{ $field['type'] }}" 
                                    class="form-control" 
                                    id="{{ $field['id'] ?? $field['name'] }}" 
                                    name="{{ $field['name'] }}" 
                                    value="{{ $field['value'] ?? '' }}"
                                    placeholder="{{ $field['placeholder'] ?? '' }}"
                                    @if(isset($field['required'])) required @endif
                                >
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm {{ $submitButtonClass }}" 
                        @if($submitButtonDisabled) disabled @endif>
                        {{ $submitButtonLabel }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>