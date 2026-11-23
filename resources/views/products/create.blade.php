<x-app-layout>
    <x-slot name="title">{{ __('Add Product') }}</x-slot>
    <x-slot name="header">{{ __('Add Product') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('products.index') }}">{{ __('Products') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('Add') }}</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-12 col-xl-9">
            <form method="POST" action="{{ route('products.store') }}" class="card border-0 shadow-sm">
                @csrf
                <div class="card-body p-4">@include('products._form')</div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <x-primary-button>{{ __('Add Product') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
