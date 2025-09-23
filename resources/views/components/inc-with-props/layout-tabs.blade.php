@props([
    'tabs' => [],
    'baseRoute' => '',
    'baseRouteParams' => [],
    'activeTab' => null,
    'tabId' => 'tabs',
    'sticky' => false,
    'secondary' => false,
    'disabled' => false
])

<ul class="nav {{ $secondary ? 'nav-pills' : 'nav-tabs' }} mb-2 {{ $sticky ? 'nav-tabs-sticky-top' : '' }}" 
    id="{{ $tabId }}" role="tablist">
    @foreach($tabs as $tab)
        @php
            // Determine if tab is active
            $isActive = $activeTab 
                ? ($activeTab === $tab['id']) 
                : (request()->routeIs($tab['route']) || request()->is($tab['path'] ?? ''));
            
            // Merge base params with tab-specific params
            $routeParams = array_merge($baseRouteParams, $tab['params'] ?? []);
            
            // Ensure all required parameters are present
            foreach ($routeParams as $key => $value) {
                if (is_null($value)) {
                    unset($routeParams[$key]);
                }
            }
            
            $icon = $tab['icon'] ?? '';
            $label = $tab['label'] ?? '';
            $permission = $tab['permission'] ?? '';
        @endphp
        
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $secondary ? 'secondary-tab' : '' }} 
                d-flex d-lg-block flex-column flex-lg-row align-items-center px-2 px-md-3
                {{ $isActive ? 'active' : '' }} 
                @if($permission) @cannot($permission) disabled @endcannot @endif"
                @if($permission) @cannot($permission) disabled @endcannot @endif
                href="{{ route($tab['route'], $routeParams) }}"
                id="{{ $tab['id'] }}-tab">
                @if($icon)
                    <i class="{{ $icon }} me-1"></i>
                @endif
                {!! $label !!}
            </a>
        </li>
    @endforeach
</ul>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const currentPath = window.location.pathname;
    const basePath = "{{ isset($baseRoute) ? route($baseRoute, $baseRouteParams) : '' }}".replace(window.location.origin, '');
    
    const navLinks = document.querySelectorAll('#{{ $tabId }} .nav-link');
    
    navLinks.forEach(link => {
        link.classList.remove('active');
        
        const linkHref = link.getAttribute('href');
        const linkPath = new URL(linkHref, window.location.origin).pathname;
        
        // For secondary tabs, check exact match first, then startsWith
        if (currentPath === linkPath) {
            link.classList.add('active');
        } 
        // Special handling for the base route (general tab)
        else if (link.id === 'general-tab' && currentPath === basePath) {
            link.classList.add('active');
        }
        // For other tabs, check if current path starts with the link path
        else if (currentPath.startsWith(linkPath) && linkPath !== basePath) {
            link.classList.add('active');
        }
    });
});
</script>
@endpush