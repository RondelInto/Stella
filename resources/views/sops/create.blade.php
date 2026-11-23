<x-app-layout>
    <x-slot name="title">{{ __('New SOP') }}</x-slot>
    <x-slot name="header">{{ __('New SOP') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('sops.index') }}">{{ __('SOP Library') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('New') }}</li>
        </ol>
    </nav>

    <form method="POST" action="{{ route('sops.store') }}" class="card border-0 shadow-sm">
        @csrf
        <div class="card-body p-4">@include('sops._form')</div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('sops.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <x-primary-button>{{ __('Save SOP') }}</x-primary-button>
        </div>
    </form>
</x-app-layout>
