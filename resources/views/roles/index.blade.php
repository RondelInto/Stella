<x-app-layout>
    <x-slot name="title">{{ __('Roles') }}</x-slot>
    <x-slot name="header">{{ __('Roles and Responsibilities') }}</x-slot>

    <div class="alert alert-light border small">
        <i class="bi bi-info-circle me-1"></i>
        {{ __('The system has four fixed roles. You can edit their names, descriptions, and responsibilities. What each role is allowed to do in the system is listed under "System access".') }}
    </div>

    <div class="row g-4">
        @foreach ($roles as $role)
            <div class="col-12 col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <h2 class="h5 mb-0">{{ $role->name }}</h2>
                                <span class="small text-muted">
                                    {{ trans_choice(':count active employee|:count active employees', $role->active_users_count) }}
                                </span>
                            </div>
                            <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i> {{ __('Edit') }}
                            </a>
                        </div>

                        @if ($role->description)
                            <p class="mb-2">{{ $role->description }}</p>
                        @endif

                        @if ($role->responsibilities)
                            <h3 class="h6 small text-uppercase text-muted mt-3">{{ __('Responsibilities') }}</h3>
                            <p class="small mb-0" style="white-space: pre-line">{{ $role->responsibilities }}</p>
                        @endif

                        <h3 class="h6 small text-uppercase text-muted mt-3">{{ __('System access') }}</h3>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($permissionsByRole[$role->id] as $permission)
                                <span class="badge text-bg-light border fw-normal">{{ str_replace('-', ' ', $permission) }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
