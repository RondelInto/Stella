<x-app-layout>
    <x-slot name="title">{{ __('Stock Discrepancies') }}</x-slot>
    <x-slot name="header">{{ __('Stock Discrepancies') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('stock-counts.index') }}">{{ __('Stock Counts') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('Discrepancies') }}</li>
        </ol>
    </nav>

    <p class="text-muted">{{ __('Items whose physical count did not match the system during completed stock counts.') }}</p>

    <form method="GET" action="{{ route('stock-counts.discrepancies') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                @if ($locations->count() > 1)
                    <div class="col-6 col-md-3">
                        <label for="location" class="form-label small mb-1">{{ __('Location') }}</label>
                        <select id="location" name="location" class="form-select">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected(($filters['location'] ?? '') == $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-6 col-md-3">
                    <label for="search" class="form-label small mb-1">{{ __('Item') }}</label>
                    <input type="search" id="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}"
                           placeholder="{{ __('Name or code') }}">
                </div>
                <div class="col-6 col-md-2">
                    <label for="from" class="form-label small mb-1">{{ __('From') }}</label>
                    <input type="date" id="from" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-6 col-md-2">
                    <label for="to" class="form-label small mb-1">{{ __('To') }}</label>
                    <input type="date" id="to" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
                    <a href="{{ route('stock-counts.discrepancies') }}" class="btn btn-outline-secondary" title="{{ __('Clear filters') }}"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Count Date') }}</th>
                        <th>{{ __('Location') }}</th>
                        <th>{{ __('Item') }}</th>
                        <th class="text-end">{{ __('System') }}</th>
                        <th class="text-end">{{ __('Counted') }}</th>
                        <th class="text-end">{{ __('Variance') }}</th>
                        <th>{{ __('Counted By') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($details as $detail)
                        <tr>
                            <td class="text-nowrap">{{ $detail->stockCount->count_date->format('M d, Y') }}</td>
                            <td>{{ $detail->stockCount->location->name }}</td>
                            <td>
                                <a href="{{ route('inventory-items.show', $detail->inventoryItem) }}" class="text-decoration-none">{{ $detail->inventoryItem->name }}</a>
                                <div class="small text-muted"><code>{{ $detail->inventoryItem->item_code }}</code></div>
                            </td>
                            <td class="text-end"><x-qty :value="$detail->system_quantity" /></td>
                            <td class="text-end"><x-qty :value="$detail->counted_quantity" /></td>
                            <td class="text-end text-nowrap"><x-variance :value="$detail->variance" :unit="$detail->inventoryItem->unit_of_measure" /></td>
                            <td>{{ $detail->stockCount->countedBy->name }}</td>
                            <td class="text-end">
                                <a href="{{ route('stock-counts.show', $detail->stockCount) }}" class="btn btn-sm btn-outline-secondary">#{{ $detail->stock_count_id }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No discrepancies found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($details->hasPages())
            <div class="card-footer bg-white">{{ $details->links() }}</div>
        @endif
    </div>
</x-app-layout>
