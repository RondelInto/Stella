<x-app-layout>
    <x-slot name="title">{{ __('Stock Levels') }}</x-slot>
    <x-slot name="header">{{ __('Stock Levels') }}</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">
            @if ($selected)
                {{ __('Stock on hand at :location.', ['location' => $selected->name]) }}
            @else
                {{ __('Stock on hand at all locations.') }}
            @endif
        </p>
        @can('record-inventory-transactions')
            <a href="{{ route('inventory-transactions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>{{ __('Record Transaction') }}
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('stock-levels.index') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                @if ($isManagement)
                    <div class="col-12 col-md-3">
                        <label for="location" class="form-label small mb-1">{{ __('Location') }}</label>
                        <select id="location" name="location" class="form-select">
                            <option value="">{{ __('All locations') }}</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected($selected?->id === $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-12 col-md-3">
                    <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                    <input type="search" id="search" name="search" class="form-control"
                           value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Item name or code') }}">
                </div>
                <div class="col-6 col-md-2">
                    <label for="category" class="form-label small mb-1">{{ __('Category') }}</label>
                    <select id="category" name="category" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(($filters['category'] ?? '') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="type" class="form-label small mb-1">{{ __('Type') }}</label>
                    <select id="type" name="type" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
                    <a href="{{ route('stock-levels.index') }}" class="btn btn-outline-secondary" title="{{ __('Clear filters') }}"><i class="bi bi-x-lg"></i></a>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="low" value="1" id="low"
                               @checked($filters['low'] ?? false) @disabled(! $selected)>
                        <label class="form-check-label small" for="low">
                            {{ __('Show low stock only') }}
                            @unless ($selected)
                                <span class="text-muted">({{ __('select one location first') }})</span>
                            @endunless
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Item') }}</th>
                        <th>{{ __('Category') }}</th>
                        <th class="text-end">{{ __('Reorder Level') }}</th>
                        @foreach ($shownLocations as $location)
                            <th class="text-end text-nowrap">{{ $shownLocations->count() > 1 ? $location->name : __('On Hand') }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td class="text-nowrap"><code>{{ $item->item_code }}</code></td>
                            <td>
                                <a href="{{ route('inventory-items.show', $item) }}" class="text-decoration-none fw-semibold">{{ $item->name }}</a>
                                <div><x-item-type-badge :type="$item->item_type" /></div>
                            </td>
                            <td>{{ $item->category->name }}</td>
                            <td class="text-end text-muted"><x-qty :value="$item->reorder_level" /></td>
                            @foreach ($shownLocations as $location)
                                @php
                                    $qty = (float) ($stocks->get($item->id)?->get($location->id) ?? 0);
                                    $low = $qty <= (float) $item->reorder_level;
                                @endphp
                                <td class="text-end text-nowrap {{ $low ? 'text-danger fw-semibold' : '' }}">
                                    <x-qty :value="$qty" :unit="$item->unit_of_measure" />
                                    @if ($low)
                                        <i class="bi bi-exclamation-triangle-fill ms-1" title="{{ __('Low stock') }}" aria-label="{{ __('Low stock') }}"></i>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 4 + $shownLocations->count() }}" class="text-center text-muted py-4">{{ __('No items found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($items->hasPages())
            <div class="card-footer bg-white">{{ $items->links() }}</div>
        @endif
    </div>

    <p class="small text-muted mt-2">
        <i class="bi bi-exclamation-triangle-fill text-danger"></i>
        {{ __('Red = at or below the reorder level.') }}
    </p>
</x-app-layout>
