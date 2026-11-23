<x-app-layout>
    <x-slot name="title">{{ __('New Transfer') }}</x-slot>
    <x-slot name="header">{{ __('New Transfer') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('stock-transfers.index') }}">{{ __('Stock Transfers') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('New') }}</li>
        </ol>
    </nav>

    <form method="POST" action="{{ route('stock-transfers.store') }}" class="card border-0 shadow-sm">
        @csrf
        <div class="card-body p-4">@include('transfers._form')</div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('stock-transfers.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <x-primary-button>{{ __('Create Transfer') }}</x-primary-button>
        </div>
    </form>
</x-app-layout>
