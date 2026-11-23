<x-app-layout>
    <x-slot name="title">{{ __('Production History') }}</x-slot>
    <x-slot name="header">{{ __('Production History') }}</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">{{ __('Food produced at the commissary, with the ingredients used.') }}</p>
        <div class="d-flex gap-2">
            <a href="{{ route('production.planning') }}" class="btn btn-outline-primary"><i class="bi bi-clipboard-data me-1"></i>{{ __('Planning') }}</a>
            @can('record-production')
                <a href="{{ route('production.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Record Production') }}</a>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('production.index') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="product" class="form-label small mb-1">{{ __('Product') }}</label>
                    <select id="product" name="product" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected(($filters['product'] ?? '') == $product->id)>{{ $product->pos_product_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="from" class="form-label small mb-1">{{ __('From') }}</label>
                    <input type="date" id="from" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-6 col-md-3">
                    <label for="to" class="form-label small mb-1">{{ __('To') }}</label>
                    <input type="date" id="to" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
                    <a href="{{ route('production.index') }}" class="btn btn-outline-secondary" title="{{ __('Clear filters') }}"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Product') }}</th>
                        <th class="text-end">{{ __('Quantity Made') }}</th>
                        <th class="text-center">{{ __('Ingredients') }}</th>
                        <th>{{ __('Recorded By') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>{{ $record->id }}</td>
                            <td class="text-nowrap">{{ $record->production_date->format('M d, Y') }}</td>
                            <td class="fw-semibold">{{ $record->product->pos_product_name }}</td>
                            <td class="text-end"><x-qty :value="$record->quantity_produced" :unit="$record->product->inventoryItem?->unit_of_measure" /></td>
                            <td class="text-center">{{ $record->details_count }}</td>
                            <td>{{ $record->user->name }}</td>
                            <td class="text-end"><a href="{{ route('production.show', $record) }}" class="btn btn-sm btn-outline-primary">{{ __('View') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No production recorded yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($records->hasPages())
            <div class="card-footer bg-white">{{ $records->links() }}</div>
        @endif
    </div>
</x-app-layout>
