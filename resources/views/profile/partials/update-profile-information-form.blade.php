<section>
    <header class="mb-3">
        <h2 class="h6 mb-1">{{ __('Profile Information') }}</h2>
        <p class="text-muted small mb-0">{{ __('Update your name and contact details.') }}</p>
    </header>

    {{-- Account details managed by Management (read-only here) --}}
    <dl class="row small mb-4">
        <dt class="col-sm-3 text-muted fw-normal">{{ __('Username') }}</dt>
        <dd class="col-sm-9">{{ $user->username }}</dd>

        <dt class="col-sm-3 text-muted fw-normal">{{ __('Role') }}</dt>
        <dd class="col-sm-9">{{ $user->activeRoles->pluck('name')->join(', ') ?: __('No role assigned') }}</dd>

        <dt class="col-sm-3 text-muted fw-normal">{{ __('Location') }}</dt>
        <dd class="col-sm-9 mb-0">{{ $user->location?->name ?? __('All locations') }}</dd>
    </dl>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <x-input-label for="first_name" :value="__('First Name')" />
                <x-text-input id="first_name" name="first_name" type="text" :value="old('first_name', $user->first_name)"
                              :invalid="$errors->has('first_name')" required autocomplete="given-name" />
                <x-input-error :messages="$errors->get('first_name')" />
            </div>
            <div class="col-md-6">
                <x-input-label for="last_name" :value="__('Last Name')" />
                <x-text-input id="last_name" name="last_name" type="text" :value="old('last_name', $user->last_name)"
                              :invalid="$errors->has('last_name')" required autocomplete="family-name" />
                <x-input-error :messages="$errors->get('last_name')" />
            </div>
        </div>

        <div class="mb-3">
            <x-input-label for="email" :value="__('Email (optional)')" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)"
                          :invalid="$errors->has('email')" autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && $user->email && ! $user->hasVerifiedEmail())
                <p class="small mt-2 mb-0">
                    {{ __('Your email address is unverified.') }}
                    <button form="send-verification" class="btn btn-link btn-sm p-0 align-baseline">
                        {{ __('Click here to re-send the verification email.') }}
                    </button>
                </p>

                @if (session('status') === 'verification-link-sent')
                    <p class="small text-success mt-2 mb-0">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </p>
                @endif
            @endif
        </div>

        <div class="mb-3">
            <x-input-label for="contact_no" :value="__('Contact Number (optional)')" />
            <x-text-input id="contact_no" name="contact_no" type="text" :value="old('contact_no', $user->contact_no)"
                          :invalid="$errors->has('contact_no')" autocomplete="tel" />
            <x-input-error :messages="$errors->get('contact_no')" />
        </div>

        <div class="d-flex align-items-center gap-3">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <span class="text-success small"><i class="bi bi-check-circle me-1"></i>{{ __('Saved.') }}</span>
            @endif
        </div>
    </form>
</section>
