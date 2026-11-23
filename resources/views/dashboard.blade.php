<x-app-layout>
    <x-slot name="title">{{ __('Dashboard') }}</x-slot>
    <x-slot name="header">{{ __('Dashboard') }}</x-slot>

    {{--
        Summary cards are added here as each module is built.
        Only REAL counts from the database are shown; never sample numbers.
    --}}

    @if (Auth::user()->activeRoles->isEmpty())
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ __('Your account does not have a role yet, so no system modules are available. Please contact management.') }}
        </div>
    @endif

    <div class="mb-4">
        <h2 class="h5 mb-1">{{ __('Welcome, :name!', ['name' => Auth::user()->name]) }}</h2>
        <p class="text-muted mb-0">
            {{ Auth::user()->activeRoles->pluck('name')->join(', ') ?: __('No role') }}
            &middot; {{ Auth::user()->location?->name ?? __('All locations') }}
        </p>
    </div>

    @isset($stats['employees'])
        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <a href="{{ route('users.index', ['status' => 'active']) }}" class="card border-0 shadow-sm text-decoration-none h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ __('Active Employees') }}</div>
                        <div class="fs-3 fw-semibold text-body">{{ $stats['employees']['active'] }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-lg-3">
                <a href="{{ route('users.index', ['status' => 'inactive']) }}" class="card border-0 shadow-sm text-decoration-none h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ __('Inactive Accounts') }}</div>
                        <div class="fs-3 fw-semibold text-body">{{ $stats['employees']['inactive'] }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ __('Accounts Without a Role') }}</div>
                        <div class="fs-3 fw-semibold {{ $stats['employees']['without_role'] ? 'text-danger' : 'text-body' }}">{{ $stats['employees']['without_role'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ __('Active Locations') }}</div>
                        <div class="fs-3 fw-semibold text-body">{{ $stats['employees']['locations'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body text-muted">
                {{ __('Your modules will appear in the menu as they are added to the system.') }}
            </div>
        </div>
    @endisset
</x-app-layout>
