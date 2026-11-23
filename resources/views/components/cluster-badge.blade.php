@props(['label'])
@php
    $class = match ($label) {
        'High performer' => 'text-bg-success',
        'Medium performer' => 'text-bg-primary',
        'Low performer' => 'text-bg-danger',
        default => 'text-bg-light border',
    };
@endphp
<span {{ $attributes->merge(['class' => "badge {$class}"]) }}>{{ $label ? __($label) : __('Not grouped') }}</span>
