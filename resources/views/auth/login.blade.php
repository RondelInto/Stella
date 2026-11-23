<x-guest-layout>
    <h1 class="h5 mb-1">{{ __('Sign in') }}</h1>
    <p class="text-muted small mb-4">{{ __('Use the account provided by management.') }}</p>

    <x-auth-session-status class="mb-3" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <x-input-label for="username" :value="__('Username')" />
            <x-text-input id="username" type="text" name="username" :value="old('username')"
                          :invalid="$errors->has('username')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('username')" />
        </div>

        <div class="mb-3">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" type="password" name="password"
                          :invalid="$errors->has('password')" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="form-check mb-3">
            <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
            <label for="remember_me" class="form-check-label small">{{ __('Remember me') }}</label>
        </div>

        <div class="d-grid mb-3">
            <x-primary-button>{{ __('Log in') }}</x-primary-button>
        </div>

        @if (Route::has('password.request'))
            <div class="text-center">
                <a class="small" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
            </div>
        @endif
    </form>
</x-guest-layout>
