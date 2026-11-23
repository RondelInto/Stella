@php($category = $category ?? null)

<div class="mb-3">
    <x-input-label for="name" :value="__('Category Name')" />
    <x-text-input id="name" name="name" :value="old('name', $category?->name)" :invalid="$errors->has('name')" required />
    <x-input-error :messages="$errors->get('name')" />
</div>

<div class="mb-0">
    <x-input-label for="description" :value="__('Description (optional)')" />
    <x-text-input id="description" name="description" :value="old('description', $category?->description)"
                  :invalid="$errors->has('description')" />
    <x-input-error :messages="$errors->get('description')" />
</div>
