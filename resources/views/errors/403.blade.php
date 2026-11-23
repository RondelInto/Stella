{{-- Shown when a user opens a page their role is not allowed to access. --}}
@auth
    <x-app-layout>
        <x-slot name="title">{{ __('Access Denied') }}</x-slot>
        <x-slot name="header">{{ __('Access Denied') }}</x-slot>

        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-shield-lock display-4 text-secondary" aria-hidden="true"></i>
                <h2 class="h5 mt-3">{{ __('You do not have permission to access this page.') }}</h2>
                <p class="text-muted mb-4">
                    {{ __('If you need access, please contact management to review your assigned role.') }}
                </p>
                <a href="{{ route('dashboard') }}" class="btn btn-primary">{{ __('Back to Dashboard') }}</a>
            </div>
        </div>
    </x-app-layout>
@else
    <x-guest-layout>
        <h1 class="h5">{{ __('Access Denied') }}</h1>
        <p class="text-muted small">{{ __('You do not have permission to access this page.') }}</p>
        <a href="{{ route('login') }}" class="btn btn-primary w-100">{{ __('Go to Login') }}</a>
    </x-guest-layout>
@endauth
