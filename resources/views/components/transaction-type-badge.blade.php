{{-- Badge for an inventory transaction type. Usage: <x-transaction-type-badge :type="$tx->transaction_type" /> --}}
@props(['type'])

@php
    [$label, $class, $icon] = match ($type) {
        'received' => ['Received', 'text-bg-success', 'box-arrow-in-down'],
        'issued' => ['Issued', 'text-bg-warning', 'box-arrow-up'],
        'damaged' => ['Damaged / Spoiled', 'text-bg-danger', 'exclamation-triangle'],
        'transfer_out' => ['Transfer Out', 'text-bg-secondary', 'arrow-right-circle'],
        'transfer_in' => ['Transfer In', 'text-bg-primary', 'arrow-left-circle'],
        'production_use' => ['Used in Production', 'text-bg-warning', 'fire'],
        'production_output' => ['Production Output', 'text-bg-success', 'basket'],
        'count_adjustment' => ['Count Adjustment', 'text-bg-info', 'clipboard-check'],
        default => [$type, 'text-bg-light', 'question'],
    };
@endphp

<span {{ $attributes->merge(['class' => "badge fw-normal {$class}"]) }}>
    <i class="bi bi-{{ $icon }} me-1" aria-hidden="true"></i>{{ __($label) }}
</span>
