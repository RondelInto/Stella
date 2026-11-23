{{--
    Sidebar navigation.
    Add a link for each module as it is built, e.g.:
    <x-nav-link :href="route('inventory.index')" :active="request()->routeIs('inventory.*')" icon="box-seam">Inventory</x-nav-link>
    Links for restricted modules must also be wrapped in @can(...) once roles are implemented (Phase 3).
    Hiding a link is NOT security; routes must also be protected on the server.
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
            <nav class="nav flex-column gap-1 w-100">
                <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="speedometer2">
                    {{ __('Dashboard') }}
                </x-nav-link>

                <div class="sidebar-heading">{{ __('Account') }}</div>
                <x-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')" icon="person">
                    {{ __('My Profile') }}
                </x-nav-link>
            </nav>
        </div>
    </div>
</aside>
