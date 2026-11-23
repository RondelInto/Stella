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

    @isset($stats['low_stock'])
        <h3 class="h6 text-uppercase text-muted small">{{ __('Inventory') }}</h3>
        <div class="row g-3 mb-4">
            @foreach ($stats['low_stock'] as $row)
                <div class="col-6 col-lg-3">
                    <a href="{{ route('stock-levels.index', ['location' => $row['location']->id, 'low' => 1]) }}"
                       class="card border-0 shadow-sm text-decoration-none h-100">
                        <div class="card-body">
                            <div class="text-muted small text-truncate">{{ __('Low Stock') }} · {{ $row['location']->name }}</div>
                            <div class="fs-3 fw-semibold {{ $row['count'] ? 'text-danger' : 'text-body' }}">{{ $row['count'] }}</div>
                            <div class="small text-muted">{{ __('items at or below reorder level') }}</div>
                        </div>
                    </a>
                </div>
            @endforeach
            <div class="col-6 col-lg-3">
                <a href="{{ route('inventory-transactions.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]) }}"
                   class="card border-0 shadow-sm text-decoration-none h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ __('Stock Movements Today') }}</div>
                        <div class="fs-3 fw-semibold text-body">{{ $stats['transactions_today'] }}</div>
                    </div>
                </a>
            </div>
        </div>
    @endisset

    @isset($stats['transfers'])
        <h3 class="h6 text-uppercase text-muted small">{{ __('Stock Transfers') }}</h3>
        <div class="row g-3 mb-4">
            @if ($stats['transfers']['to_receive'] !== null)
                <div class="col-6 col-lg-3">
                    <a href="{{ route('stock-transfers.index', ['status' => 'released']) }}" class="card border-0 shadow-sm text-decoration-none h-100">
                        <div class="card-body">
                            <div class="text-muted small">{{ __('To Receive at Your Branch') }}</div>
                            <div class="fs-3 fw-semibold {{ $stats['transfers']['to_receive'] ? 'text-primary' : 'text-body' }}">{{ $stats['transfers']['to_receive'] }}</div>
                        </div>
                    </a>
                </div>
            @endif
            <div class="col-6 col-lg-3">
                <a href="{{ route('stock-transfers.index', ['status' => 'pending']) }}" class="card border-0 shadow-sm text-decoration-none h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ __('Pending (not yet released)') }}</div>
                        <div class="fs-3 fw-semibold text-body">{{ $stats['transfers']['pending'] }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-lg-3">
                <a href="{{ route('stock-transfers.index', ['status' => 'released']) }}" class="card border-0 shadow-sm text-decoration-none h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ __('In Transit') }}</div>
                        <div class="fs-3 fw-semibold text-body">{{ $stats['transfers']['in_transit'] }}</div>
                    </div>
                </a>
            </div>
        </div>
    @endisset

    @isset($stats['production_today'])
        <h3 class="h6 text-uppercase text-muted small">{{ __('Production') }}</h3>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <a href="{{ route('production.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]) }}"
                   class="card border-0 shadow-sm text-decoration-none h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ __('Batches Recorded Today') }}</div>
                        <div class="fs-3 fw-semibold text-body">{{ $stats['production_today'] }}</div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-lg-3">
                <a href="{{ route('production.planning') }}" class="card border-0 shadow-sm text-decoration-none h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ __('Production Planning') }}</div>
                        <div class="fs-6 text-body mt-2"><i class="bi bi-clipboard-data me-1"></i>{{ __('See remaining stock') }}</div>
                    </div>
                </a>
            </div>
        </div>
    @endisset

    @isset($stats['sops'])
        <h3 class="h6 text-uppercase text-muted small">{{ __('Procedures') }}</h3>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <a href="{{ route('sops.index') }}" class="card border-0 shadow-sm text-decoration-none h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ __('SOPs Available to You') }}</div>
                        <div class="fs-3 fw-semibold text-body">{{ $stats['sops']['count'] }}</div>
                    </div>
                </a>
            </div>
            @if ($stats['sops']['recent']->isNotEmpty())
                <div class="col-12 col-lg-9">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-1">{{ __('New or updated in the last 7 days') }}</div>
                            <ul class="list-unstyled mb-0 small">
                                @foreach ($stats['sops']['recent'] as $recentSop)
                                    <li>
                                        <a href="{{ route('sops.show', $recentSop) }}">{{ $recentSop->title }}</a>
                                        <span class="text-muted">· v{{ $recentSop->version_no }} · {{ $recentSop->updated_at->format('M d') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endisset

    @isset($stats['employees'])
        <h3 class="h6 text-uppercase text-muted small">{{ __('Administration') }}</h3>
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
    @endisset

    @if (empty($stats))
        <div class="card border-0 shadow-sm">
            <div class="card-body text-muted">
                {{ __('Your modules will appear in the menu as they are added to the system.') }}
            </div>
        </div>
    @endif
</x-app-layout>
