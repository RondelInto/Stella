<x-app-layout>
    <x-slot name="title">{{ __('Edit Transfer') }}</x-slot>
    <x-slot name="header">{{ __('Edit Transfer :ref', ['ref' => $transfer->reference_no]) }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('stock-transfers.index') }}">{{ __('Stock Transfers') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('stock-transfers.show', $transfer) }}">{{ $transfer->reference_no }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('Edit') }}</li>
        </ol>
    </nav>

    <form method="POST" action="{{ route('stock-transfers.update', $transfer) }}" class="card border-0 shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body p-4">@include('transfers._form')</div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('stock-transfers.show', $transfer) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
        </div>
    </form>
</x-app-layout>
