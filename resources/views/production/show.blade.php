<x-app-layout>
    <x-slot name="title">{{ __('Production #:id', ['id' => $record->id]) }}</x-slot>
    <x-slot name="header">{{ __('Production #:id', ['id' => $record->id]) }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('production.index') }}">{{ __('Production History') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">#{{ $record->id }}</li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h4 mb-1">{{ $record->product->pos_product_name }}</h2>
            <div class="fs-5 mb-2">
                <x-qty :value="$record->quantity_produced" :unit="$record->product->inventoryItem?->unit_of_measure" />
                <span class="text-muted fs-6">{{ __('made') }}</span>
            </div>
            <div class="d-flex flex-wrap gap-4 small">
                <div><span class="text-muted">{{ __('Production date') }}</span><div>{{ $record->production_date->format('M d, Y') }}</div></div>
                <div><span class="text-muted">{{ __('Location') }}</span><div>{{ $record->location->name }}</div></div>
                <div><span class="text-muted">{{ __('Recorded by') }}</span><div>{{ $record->user->name }}</div></div>
                <div><span class="text-muted">{{ __('Recorded at') }}</span><div>{{ $record->created_at->format('M d, Y h:i A') }}</div></div>
                @if ($record->remarks)
                    <div><span class="text-muted">{{ __('Remarks') }}</span><div>{{ $record->remarks }}</div></div>
                @endif
            </div>
        </div>
    </div>

    <h3 class="h6 text-uppercase text-muted small">{{ __('Ingredients Used') }}</h3>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Ingredient') }}</th>
                        <th class="text-end">{{ __('Quantity Used') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($record->details as $detail)
                        <tr>
                            <td><code>{{ $detail->inventoryItem->item_code }}</code></td>
                            <td><a href="{{ route('inventory-items.show', $detail->inventoryItem) }}" class="text-decoration-none">{{ $detail->inventoryItem->name }}</a></td>
                            <td class="text-end"><x-qty :value="$detail->quantity_used" :unit="$detail->inventoryItem->unit_of_measure" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
