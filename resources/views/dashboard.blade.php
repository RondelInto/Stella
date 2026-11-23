<x-app-layout>
    <x-slot name="title">{{ __('Dashboard') }}</x-slot>
    <x-slot name="header">{{ __('Dashboard') }}</x-slot>

    {{--
        Phase 1 placeholder.
        Summary cards (stock levels, pending transfers, production, etc.) will be added here
        once the inventory and production modules exist. Do not show sample numbers here.
    --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5">{{ __('Welcome, :name!', ['name' => Auth::user()->name]) }}</h2>
            <p class="text-muted mb-0">
                {{ __('You are logged in. Dashboard summaries will appear here as system modules are added.') }}
            </p>
        </div>
    </div>
</x-app-layout>
