<x-app-layout>
    <x-slot name="title">{{ __('Edit Item') }}</x-slot>
    <x-slot name="header">{{ __('Edit Item') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('inventory-items.index') }}">{{ __('Items') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('inventory-items.show', $item) }}">{{ $item->name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('Edit') }}</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-12 col-xl-9">
            <form method="POST" action="{{ route('inventory-items.update', $item) }}" class="card border-0 shadow-sm">
                @csrf
                @method('PUT')
                <div class="card-body p-4">
                    <div class="alert alert-light border small">
                        <i class="bi bi-info-circle me-1"></i>
                        {{ __('Stock quantities cannot be edited here. They only change through recorded transactions, transfers, production, and stock counts.') }}
                    </div>
                    @include('inventory.items._form')
                </div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('inventory-items.show', $item) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
