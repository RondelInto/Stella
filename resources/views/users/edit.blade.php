<x-app-layout>
    <x-slot name="title">{{ __('Edit Employee') }}</x-slot>
    <x-slot name="header">{{ __('Edit Employee') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('users.index') }}">{{ __('Employees') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $user->name }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        {{-- Main details --}}
        <div class="col-12 col-xl-8">
            <form method="POST" action="{{ route('users.update', $user) }}" class="card border-0 shadow-sm">
                @csrf
                @method('PUT')
                <div class="card-body p-4">
                    @include('users._form')
                </div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
                </div>
            </form>
        </div>

        <div class="col-12 col-xl-4">
            {{-- Account status --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h6">{{ __('Account Status') }}</h2>
                    <p class="mb-3">
                        @if ($user->isActive())
                            <span class="badge text-bg-success">{{ __('Active') }}</span>
                        @else
                            <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                        @endif
                    </p>

                    @if ($user->isActive())
                        <p class="small text-muted">
                            {{ __('A deactivated employee cannot log in. Their records stay in the system.') }}
                        </p>
                        <form method="POST" action="{{ route('users.status', $user) }}"
                              data-confirm="{{ __('Deactivate :name? They will be logged out and unable to log in.', ['name' => $user->name]) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="account_status" value="inactive">
                            <button type="submit" class="btn btn-outline-danger w-100" @disabled($user->is(Auth::user()))>
                                <i class="bi bi-person-x me-1"></i>{{ __('Deactivate Account') }}
                            </button>
                        </form>
                        @if ($user->is(Auth::user()))
                            <p class="small text-muted mt-2 mb-0">{{ __('You cannot deactivate your own account.') }}</p>
                        @endif
                    @else
                        <form method="POST" action="{{ route('users.status', $user) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="account_status" value="active">
                            <button type="submit" class="btn btn-outline-success w-100">
                                <i class="bi bi-person-check me-1"></i>{{ __('Reactivate Account') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Reset password --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6">{{ __('Reset Password') }}</h2>
                    <p class="small text-muted">{{ __('Set a new password if the employee forgot theirs.') }}</p>

                    <form method="POST" action="{{ route('users.password', $user) }}"
                          data-confirm="{{ __('Reset the password for :name?', ['name' => $user->name]) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-2">
                            <x-input-label for="new_password" :value="__('New Password')" />
                            <x-text-input id="new_password" name="password" type="password" autocomplete="new-password"
                                          :invalid="$errors->resetPassword->has('password')" required />
                            <x-input-error :messages="$errors->resetPassword->get('password')" />
                        </div>
                        <div class="mb-3">
                            <x-input-label for="new_password_confirmation" :value="__('Confirm New Password')" />
                            <x-text-input id="new_password_confirmation" name="password_confirmation" type="password"
                                          autocomplete="new-password" required />
                        </div>
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="bi bi-key me-1"></i>{{ __('Reset Password') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
