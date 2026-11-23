{{--
    NOTE: Breeze lets users delete their own account. For Stella's Cuisine, accounts should be
    managed by Management, and deleting users would break inventory audit trails.
    This section will be removed or replaced in Phase 3 (Authentication and Authorization).
--}}
<section>
    <header class="mb-3">
        <h2 class="h6 mb-1 text-danger">{{ __('Delete Account') }}</h2>
        <p class="text-muted small mb-0">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted.') }}
        </p>
    </header>

    <x-danger-button type="button" data-bs-toggle="modal" data-bs-target="#confirmUserDeletion">
        {{ __('Delete Account') }}
    </x-danger-button>

    <div class="modal fade" id="confirmUserDeletion" tabindex="-1" aria-labelledby="confirmUserDeletionLabel"
         aria-hidden="true" data-show-on-load="{{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }}">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" action="{{ route('profile.destroy') }}" class="modal-content">
                @csrf
                @method('delete')

                <div class="modal-header">
                    <h2 class="modal-title h6" id="confirmUserDeletionLabel">
                        {{ __('Are you sure you want to delete your account?') }}
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="small text-muted">
                        {{ __('Please enter your password to confirm you would like to permanently delete your account.') }}
                    </p>
                    <x-input-label for="delete_password" :value="__('Password')" class="visually-hidden" />
                    <x-text-input id="delete_password" name="password" type="password" placeholder="{{ __('Password') }}"
                                  :invalid="$errors->userDeletion->has('password')" />
                    <x-input-error :messages="$errors->userDeletion->get('password')" />
                </div>

                <div class="modal-footer">
                    <x-secondary-button data-bs-dismiss="modal">{{ __('Cancel') }}</x-secondary-button>
                    <x-danger-button>{{ __('Delete Account') }}</x-danger-button>
                </div>
            </form>
        </div>
    </div>
</section>
