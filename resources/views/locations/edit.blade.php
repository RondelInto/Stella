<x-app-layout>
    <x-slot name="title">{{ __('Edit Location') }}</x-slot>
    <x-slot name="header">{{ __('Edit Location') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('locations.index') }}">{{ __('Locations') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $location->name }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-12 col-xl-7">
            <form method="POST" action="{{ route('locations.update', $location) }}" class="card border-0 shadow-sm">
                @csrf
                @method('PUT')
                <div class="card-body p-4">
                    <p class="small text-muted">
                        {{ __('Type') }}:
                        <strong>{{ $location->isCommissary() ? __('Commissary') : __('Branch') }}</strong>
                        <span class="d-block">{{ __('The type cannot be changed because employee roles depend on it.') }}</span>
                    </p>
                    @include('locations._form')
                </div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('locations.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
                </div>
            </form>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6">{{ __('Location Status') }}</h2>
                    <p class="mb-3">
                        @if ($location->status === 'active')
                            <span class="badge text-bg-success">{{ __('Active') }}</span>
                        @else
                            <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                        @endif
                        <span class="small text-muted ms-2">
                            {{ trans_choice(':count active employee|:count active employees', $location->active_users_count) }}
                        </span>
                    </p>

                    @if ($location->isCommissary())
                        <p class="small text-muted mb-0">{{ __('The Central Commissary is always active.') }}</p>
                    @elseif ($location->status === 'active')
                        <p class="small text-muted">
                            {{ __('An inactive branch cannot be selected for new records. Its existing records are kept.') }}
                        </p>
                        <form method="POST" action="{{ route('locations.status', $location) }}"
                              data-confirm="{{ __('Deactivate :name?', ['name' => $location->name]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="inactive">
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="bi bi-slash-circle me-1"></i>{{ __('Deactivate Location') }}
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('locations.status', $location) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="btn btn-outline-success w-100">
                                <i class="bi bi-check-circle me-1"></i>{{ __('Reactivate Location') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
