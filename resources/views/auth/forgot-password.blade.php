<x-guest-layout>
    <h1 class="h5 mb-2">{{ __('Forgot password') }}</h1>
    <p class="text-muted small mb-4">
        {{ __('Forgot your password? Enter your email address and we will email you a password reset link.') }}
    </p>

    <x-auth-session-status class="mb-3" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="mb-3">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')"
                          :invalid="$errors->has('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="d-grid mb-3">
            <x-primary-button>{{ __('Email Password Reset Link') }}</x-primary-button>
        </div>

        <div class="text-center">
            <a class="small" href="{{ route('login') }}">{{ __('Back to login') }}</a>
        </div>
    </form>
</x-guest-layout>
