{{-- Colored badge for an inventory item type. Usage: <x-item-type-badge :type="$item->item_type" /> --}}
@props(['type'])

@php
    [$label, $class] = match ($type) {
        'ingredient' => ['Ingredient', 'text-bg-light border'],
        'in_preparation' => ['In Preparation', 'text-bg-info'],
        'finished_product' => ['Finished Product', 'text-bg-success'],
        default => [$type, 'text-bg-secondary'],
    };
@endphp

<span {{ $attributes->merge(['class' => "badge fw-normal {$class}"]) }}>{{ __($label) }}</span>
