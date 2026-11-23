{{-- Signed variance: red when stock was missing, green when extra stock was found, muted when it matched. --}}
@props(['value', 'unit' => null])

@php($v = (float) $value)

@if ($v === 0.0)
    <span {{ $attributes->merge(['class' => 'text-muted']) }}>0</span>
@else
    <span {{ $attributes->merge(['class' => 'fw-semibold '.($v < 0 ? 'text-danger' : 'text-success')]) }}>
        {{ $v < 0 ? '−' : '+' }}<x-qty :value="abs($v)" :unit="$unit" />
    </span>
@endif
