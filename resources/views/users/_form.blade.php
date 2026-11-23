{{--
    Shared employee form for create and edit.
    Variables: $roles, $locations, and $user (only when editing).
--}}
@php
    $user = $user ?? null;
    $selectedRoles = array_map('intval', old('roles', $user?->activeRoles->pluck('id')->all() ?? []));
@endphp

<h2 class="h6 text-muted text-uppercase small mb-3">{{ __('Personal Information') }}</h2>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <x-input-label for="first_name" :value="__('First Name')" />
        <x-text-input id="first_name" name="first_name" :value="old('first_name', $user?->first_name)"
                      :invalid="$errors->has('first_name')" required />
        <x-input-error :messages="$errors->get('first_name')" />
    </div>
    <div class="col-md-6">
        <x-input-label for="last_name" :value="__('Last Name')" />
        <x-text-input id="last_name" name="last_name" :value="old('last_name', $user?->last_name)"
                      :invalid="$errors->has('last_name')" required />
        <x-input-error :messages="$errors->get('last_name')" />
    </div>
    <div class="col-md-6">
        <x-input-label for="email" :value="__('Email (optional)')" />
        <x-text-input id="email" name="email" type="email" :value="old('email', $user?->email)"
                      :invalid="$errors->has('email')" />
        <x-input-error :messages="$errors->get('email')" />
    </div>
    <div class="col-md-6">
        <x-input-label for="contact_no" :value="__('Contact Number (optional)')" />
        <x-text-input id="contact_no" name="contact_no" :value="old('contact_no', $user?->contact_no)"
                      :invalid="$errors->has('contact_no')" />
        <x-input-error :messages="$errors->get('contact_no')" />
    </div>
</div>

<h2 class="h6 text-muted text-uppercase small mb-3">{{ __('Login') }}</h2>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <x-input-label for="username" :value="__('Username')" />
        <x-text-input id="username" name="username" :value="old('username', $user?->username)"
                      :invalid="$errors->has('username')" required autocomplete="off" />
        <div class="form-text">{{ __('Lowercase letters, numbers, dots, underscores, and dashes only.') }}</div>
        <x-input-error :messages="$errors->get('username')" />
    </div>

    @unless ($user)
        <div class="col-md-6"></div>
        <div class="col-md-6">
            <x-input-label for="password" :value="__('Initial Password')" />
            <x-text-input id="password" name="password" type="password"
                          :invalid="$errors->has('password')" required autocomplete="new-password" />
            <div class="form-text">{{ __('At least 8 characters. Give it to the employee privately.') }}</div>
            <x-input-error :messages="$errors->get('password')" />
        </div>
        <div class="col-md-6">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                          required autocomplete="new-password" />
        </div>
    @endunless
</div>

<h2 class="h6 text-muted text-uppercase small mb-3">{{ __('Role and Location') }}</h2>
<div class="row g-3">
    <div class="col-md-6">
        <span class="form-label d-block">{{ __('Role(s)') }}</span>
        @foreach ($roles as $role)
            <div class="form-check mb-2">
                <input class="form-check-input @error('roles') is-invalid @enderror" type="checkbox"
                       name="roles[]" value="{{ $role->id }}" id="role_{{ $role->id }}"
                       @checked(in_array($role->id, $selectedRoles, true))>
                <label class="form-check-label" for="role_{{ $role->id }}">
                    <span class="fw-semibold">{{ $role->name }}</span>
                    @if ($role->description)
                        <span class="d-block small text-muted">{{ $role->description }}</span>
                    @endif
                </label>
            </div>
        @endforeach
        <x-input-error :messages="$errors->get('roles')" />
        <x-input-error :messages="$errors->get('roles.*')" />
    </div>

    <div class="col-md-6">
        <x-input-label for="location_id" :value="__('Assigned Location')" />
        <select id="location_id" name="location_id" class="form-select @error('location_id') is-invalid @enderror">
            <option value="">{{ __('— None (Management oversees all locations) —') }}</option>
            @foreach ($locations->groupBy('type') as $type => $group)
                <optgroup label="{{ $type === 'commissary' ? __('Commissary') : __('Branches') }}">
                    @foreach ($group as $location)
                        <option value="{{ $location->id }}" @selected((int) old('location_id', $user?->location_id) === $location->id)>
                            {{ $location->name }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <div class="form-text">
            {{ __('Required for Branch Manager, Branch Staff (a branch) and Commissary Staff (the commissary).') }}
        </div>
        <x-input-error :messages="$errors->get('location_id')" />
    </div>
</div>
