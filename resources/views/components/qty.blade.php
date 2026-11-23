{{--
    Formats a quantity without unnecessary decimals: 12.500 -> 12.5, 10.000 -> 10, 1250 -> 1,250
    Usage: <x-qty :value="$stock->quantity_on_hand" /> or <x-qty :value="$qty" unit="kg" />
--}}
@props(['value', 'unit' => null])

@php
    $formatted = rtrim(rtrim(number_format((float) $value, 3), '0'), '.');
@endphp

<span {{ $attributes }}>{{ $formatted }}@if ($unit) <span class="text-muted fw-normal">{{ $unit }}</span>@endif</span>
