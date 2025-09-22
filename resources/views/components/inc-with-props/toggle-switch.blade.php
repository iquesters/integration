@php
    $modalId = $config['modalId'] ?? 'toggleModal';
    $formId = $config['formId'] ?? 'toggleForm';
    $permission = $config['permission'] ?? null;
    $title = $config['title'] ?? 'Action Confirmation';
    $message = $config['message'] ?? 'Are you sure you want to proceed with this action?';
@endphp

<!-- Toggle Modal -->
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $modalId }}Label">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="{{ $formId }}" method="POST">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="item_id" id="modal-item-id">
                    <input type="hidden" name="action" id="modal-action">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <span id="modal-message">{{ $message }}</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-dark" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-outline-primary @if($permission && !auth()->user()->can($permission)) disabled @endif" 
                            @if($permission && !auth()->user()->can($permission)) disabled @endif 
                            id="confirm-action-btn">
                        <span id="confirm-action-text">Confirm</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Switch JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration
    const config = {
        modalId: '{{ $modalId }}',
        formId: '{{ $formId }}',
        routeTemplate: '{{ $config["routeTemplate"] ?? "" }}',
        switchClass: 'toggle-switch'
    };

    // Get elements
    const toggleSwitches = document.querySelectorAll('.' + config.switchClass);
    const modal = new bootstrap.Modal(document.getElementById(config.modalId));
    const modalTitle = document.getElementById(config.modalId + 'Label');
    const confirmActionBtn = document.getElementById('confirm-action-btn');
    const confirmActionText = document.getElementById('confirm-action-text');
    const toggleForm = document.getElementById(config.formId);
    const modalItemId = document.getElementById('modal-item-id');
    const modalAction = document.getElementById('modal-action');
    const modalMessage = document.getElementById('modal-message');

    // Store original switch states
    const originalStates = new Map();
    toggleSwitches.forEach(switchElement => {
        originalStates.set(switchElement.id, switchElement.checked);
    });

    // Add event listeners to switches
    toggleSwitches.forEach(switchElement => {
        switchElement.addEventListener('change', function(e) {
            const itemId = this.dataset.itemId;
            const itemUid = this.dataset.itemUid;
            const itemName = this.dataset.itemName || 'item';
            const isCurrentlyActive = originalStates.get(this.id);
            const newState = this.checked;
            
            // Revert switch to original state immediately
            this.checked = isCurrentlyActive;
            
            // Determine action
            const action = newState ? 'activate' : 'deactivate';
            
            // Update modal content
            modalTitle.textContent = `${action.charAt(0).toUpperCase() + action.slice(1)} ${itemName}`;
            confirmActionText.textContent = action.charAt(0).toUpperCase() + action.slice(1);
            confirmActionBtn.className = `btn btn-sm btn-outline-${action === 'activate' ? 'success' : 'danger'}`;
            
            // Set form values
            modalItemId.value = itemId;
            modalAction.value = action;
            
            // Update modal message if custom message is provided
            if (this.dataset.modalMessage) {
                modalMessage.textContent = this.dataset.modalMessage;
            }
            
            // Update form action URL
            if (config.routeTemplate) {
                toggleForm.action = config.routeTemplate.replace('__ITEM_UID__', itemUid);
            }
            
            // Show modal
            modal.show();
        });
    });

    // Handle form submission
    toggleForm.addEventListener('submit', function(e) {
        // Disable submit button to prevent double submission
        confirmActionBtn.disabled = true;
        confirmActionBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
        
        // Let the form submit normally
    });
});
</script>