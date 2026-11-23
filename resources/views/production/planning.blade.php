<x-app-layout>
    <x-slot name="title">{{ __('Production Planning') }}</x-slot>
    <x-slot name="header">{{ __('Production Planning') }}</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">
            {{ __('Remaining finished products on hand now, to help decide how much to produce.') }}
        </p>
        <div class="d-flex gap-2">
            <a href="{{ route('production.index') }}" class="btn btn-outline-secondary"><i class="bi bi-clock-history me-1"></i>{{ __('History') }}</a>
            @can('record-production')
                <a href="{{ route('production.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Record Production') }}</a>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('production.planning') }}" class="d-flex flex-wrap align-items-end gap-2 mb-3">
        <div>
            <label for="date" class="form-label small mb-1">{{ __('"Made" column shows production on') }}</label>
            <input type="date" id="date" name="date" class="form-control" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}">
        </div>
        <button type="submit" class="btn btn-outline-primary">{{ __('Show') }}</button>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Product') }}</th>
                        @foreach ($locations as $location)
                            <th class="text-end text-nowrap">{{ $location->name }}</th>
                        @endforeach
                        @if ($locations->count() > 1)
                            <th class="text-end">{{ __('Total Remaining') }}</th>
                        @endif
                        <th class="text-end text-nowrap">{{ __('Made :date', ['date' => $date->isToday() ? __('Today') : $date->format('M d')]) }}</th>
                        @if ($forecastDates)
                            <th class="text-end text-nowrap">
                                {{ __('Expected Sales') }}
                                <div class="small text-muted fw-normal">{{ $forecastDates[0]->format('M d') }} – {{ $forecastDates[1]->format('M d') }}</div>
                            </th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        @php
                            $row = $stocks->get($product->inventory_item_id) ?? collect();
                            $unit = $product->inventoryItem->unit_of_measure;
                            $total = $locations->sum(fn ($l) => (float) ($row[$l->id] ?? 0));
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $product->pos_product_name }}</td>
                            @foreach ($locations as $location)
                                <td class="text-end"><x-qty :value="$row[$location->id] ?? 0" /></td>
                            @endforeach
                            @if ($locations->count() > 1)
                                <td class="text-end fw-semibold"><x-qty :value="$total" :unit="$unit" /></td>
                            @endif
                            <td class="text-end"><x-qty :value="$produced[$product->id] ?? 0" :unit="$unit" /></td>
                            @if ($forecastDates)
                                <td class="text-end text-primary">
                                    @if ($forecast->has($product->id))
                                        <x-qty :value="$forecast[$product->id]" :unit="$unit" />
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $locations->count() + 4 }}" class="text-center text-muted py-4">
                                {{ __('No active products linked to inventory items yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="small text-muted mt-2">
        {{ __('Remaining quantities are current stock on hand. For accurate leftovers, complete the closing stock count at each location first.') }}
        @unless ($seesAllLocations)
            {{ __('You see your own branch only.') }}
        @endunless
        @if ($forecastDates)
            {{ __('Expected Sales = total forecast for the shown locations from sales forecast run #:id (regression on imported POS sales). It is a guide, not a guarantee.', ['id' => $forecastRun->id]) }}
        @else
            {{ __('No current sales forecast. Management can run one under Sales Analysis.') }}
        @endif
    </p>
</x-app-layout>
