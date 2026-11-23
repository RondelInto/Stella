{{-- Status badge for an SOP. Usage: <x-sop-status-badge :sop="$sop" /> --}}
@props(['sop'])

@php
    [$label, $class] = match (true) {
        $sop->isScheduled() => ['Scheduled', 'text-bg-info'],
        $sop->status === 'active' => ['Active', 'text-bg-success'],
        $sop->status === 'draft' => ['Draft', 'text-bg-warning'],
        $sop->status === 'archived' => ['Archived', 'text-bg-secondary'],
        default => [$sop->status, 'text-bg-light'],
    };
@endphp

<span {{ $attributes->merge(['class' => "badge {$class}"]) }}>{{ __($label) }}</span>
