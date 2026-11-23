<x-app-layout>
    <x-slot name="title">{{ __('Employees') }}</x-slot>
    <x-slot name="header">{{ __('Employees') }}</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">{{ __('Manage employee accounts, roles, and assigned locations.') }}</p>
        <a href="{{ route('users.create') }}" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>{{ __('Add Employee') }}
        </a>
    </div>

    {{-- Search and filters --}}
    <form method="GET" action="{{ route('users.index') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                    <input type="search" id="search" name="search" class="form-control"
                           value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Name, username, or email') }}">
                </div>
                <div class="col-6 col-md-2">
                    <label for="role" class="form-label small mb-1">{{ __('Role') }}</label>
                    <select id="role" name="role" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(($filters['role'] ?? '') == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="location" class="form-label small mb-1">{{ __('Location') }}</label>
                    <select id="location" name="location" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected(($filters['location'] ?? '') == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1">
                        <i class="bi bi-funnel"></i> {{ __('Filter') }}
                    </button>
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary" title="{{ __('Clear filters') }}">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </div>
        </div>
    </form>

    {{-- Employee list --}}
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Username') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Location') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $user->name }}</div>
                                @if ($user->email)
                                    <div class="small text-muted">{{ $user->email }}</div>
                                @endif
                            </td>
                            <td>{{ $user->username }}</td>
                            <td>
                                @forelse ($user->activeRoles as $role)
                                    <span class="badge text-bg-light border">{{ $role->name }}</span>
                                @empty
                                    <span class="text-muted small">{{ __('None') }}</span>
                                @endforelse
                            </td>
                            <td>{{ $user->location?->name ?? __('All locations') }}</td>
                            <td>
                                @if ($user->isActive())
                                    <span class="badge text-bg-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> {{ __('Edit') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('No employees found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="card-footer bg-white">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
