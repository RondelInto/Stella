<x-app-layout>
    <x-slot name="title">{{ __('Edit Category') }}</x-slot>
    <x-slot name="header">{{ __('Edit Category') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('inventory-categories.index') }}">{{ __('Categories') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $category->name }}</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-12 col-xl-6">
            <form method="POST" action="{{ route('inventory-categories.update', $category) }}" class="card border-0 shadow-sm">
                @csrf
                @method('PUT')
                <div class="card-body p-4">@include('inventory.categories._form')</div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('inventory-categories.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
