<x-app-layout>
    <x-slot name="title">{{ __('Add Branch') }}</x-slot>
    <x-slot name="header">{{ __('Add Branch') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('locations.index') }}">{{ __('Locations') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('Add Branch') }}</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-12 col-xl-7">
            <form method="POST" action="{{ route('locations.store') }}" class="card border-0 shadow-sm">
                @csrf
                <div class="card-body p-4">
                    <div class="alert alert-light border small">
                        <i class="bi bi-info-circle me-1"></i>
                        {{ __('New locations are added as branches. The company has one Central Commissary.') }}
                    </div>
                    @include('locations._form')
                </div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('locations.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <x-primary-button>{{ __('Add Branch') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
