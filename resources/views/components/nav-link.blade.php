{{-- Sidebar link. Usage: <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="speedometer2">Dashboard</x-nav-link> --}}
@props(['active' => false, 'icon' => null])

<a {{ $attributes->class(['nav-link', 'active' => $active]) }} @if ($active) aria-current="page" @endif>
    @if ($icon)
        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    @endif
    <span>{{ $slot }}</span>
</a>
