{{-- Shared location form. $location is only set when editing. --}}
@php($location = $location ?? null)

<div class="mb-3">
    <x-input-label for="name" :value="__('Location Name')" />
    <x-text-input id="name" name="name" :value="old('name', $location?->name)"
                  :invalid="$errors->has('name')" required placeholder="{{ __('e.g. Toril Branch') }}" />
    <x-input-error :messages="$errors->get('name')" />
</div>

<div class="mb-3">
    <x-input-label for="address" :value="__('Address (optional)')" />
    <x-text-input id="address" name="address" :value="old('address', $location?->address)"
                  :invalid="$errors->has('address')" />
    <x-input-error :messages="$errors->get('address')" />
</div>

<div class="mb-0">
    <x-input-label for="contact_no" :value="__('Contact Number (optional)')" />
    <x-text-input id="contact_no" name="contact_no" :value="old('contact_no', $location?->contact_no)"
                  :invalid="$errors->has('contact_no')" />
    <x-input-error :messages="$errors->get('contact_no')" />
</div>
