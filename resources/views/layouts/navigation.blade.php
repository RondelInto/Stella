{{--
    Sidebar navigation.
    Each module link is wrapped in @can(...) so users only see what their role allows.
    Hiding a link is NOT security: every route must ALSO use ->middleware('can:...').
    Module links are added here as each module is built in Phase 4, e.g.:

    @can('view-inventory')
        <x-nav-link :href="route('inventory.index')" :active="request()->routeIs('inventory.*')" icon="box-seam">
            {{ __('Inventory') }}
        </x-nav-link>
    @endcan
--}}
<aside class="app-sidebar">
    <div class="offcanvas-lg offcanvas-start text-white" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
        <div class="offcanvas-header border-bottom border-secondary">
            <a href="{{ route('dashboard') }}" class="text-white text-decoration-none" id="appSidebarLabel">
                <x-application-logo class="fs-5" />
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas"
                    data-bs-target="#appSidebar" aria-label="Close"></button>
        </div>

        <div class="offcanvas-body d-flex flex-column p-2">
            {{-- Who is logged in: role and location --}}
            <div class="px-2 py-2 mb-2 small border-bottom border-secondary w-100">
                <div class="fw-semibold text-white text-truncate">{{ Auth::user()->name }}</div>
                <div class="text-secondary text-truncate">
                    {{ Auth::user()->activeRoles->pluck('name')->join(', ') ?: __('No role assigned') }}
                </div>
                <div class="text-secondary text-truncate">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    {{ Auth::user()->location?->name ?? __('All locations') }}
                </div>
            </div>

            <nav class="nav flex-column gap-1 w-100">
                <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="speedometer2">
                    {{ __('Dashboard') }}
                </x-nav-link>

                {{-- Phase 4 module links go here (each wrapped in @can) --}}

                @can('view-inventory')
                    <div class="sidebar-heading">{{ __('Inventory') }}</div>
                    <x-nav-link :href="route('inventory-items.index')" :active="request()->routeIs('inventory-items.*')" icon="box-seam">
                        {{ __('Items') }}
                    </x-nav-link>
                @endcan
                @can('manage-inventory-items')
                    <x-nav-link :href="route('inventory-categories.index')" :active="request()->routeIs('inventory-categories.*')" icon="tags">
                        {{ __('Categories') }}
                    </x-nav-link>
                @endcan

                @can('manage-users')
                    <div class="sidebar-heading">{{ __('Administration') }}</div>
                    <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')" icon="people">
                        {{ __('Employees') }}
                    </x-nav-link>
                    <x-nav-link :href="route('roles.index')" :active="request()->routeIs('roles.*')" icon="person-badge">
                        {{ __('Roles') }}
                    </x-nav-link>
                @endcan
                @can('manage-locations')
                    <x-nav-link :href="route('locations.index')" :active="request()->routeIs('locations.*')" icon="geo-alt">
                        {{ __('Locations') }}
                    </x-nav-link>
                @endcan

                <div class="sidebar-heading">{{ __('Account') }}</div>
                <x-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')" icon="person">
                    {{ __('My Profile') }}
                </x-nav-link>
            </nav>
        </div>
    </div>
</aside>
