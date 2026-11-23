{{-- Status badge for a stock transfer. Usage: <x-transfer-status-badge :status="$transfer->status" /> --}}
@props(['status'])

@php
    [$label, $class, $icon] = match ($status) {
        'pending' => ['Pending', 'text-bg-warning', 'hourglass-split'],
        'released' => ['Released (in transit)', 'text-bg-primary', 'truck'],
        'received' => ['Received', 'text-bg-success', 'check-circle'],
        'cancelled' => ['Cancelled', 'text-bg-secondary', 'x-circle'],
        default => [$status, 'text-bg-light', 'question'],
    };
@endphp

<span {{ $attributes->merge(['class' => "badge {$class}"]) }}><i class="bi bi-{{ $icon }} me-1" aria-hidden="true"></i>{{ __($label) }}</span>
