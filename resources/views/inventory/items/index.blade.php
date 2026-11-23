<x-app-layout>
    <x-slot name="title">{{ __('Inventory Items') }}</x-slot>
    <x-slot name="header">{{ __('Inventory Items') }}</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">{{ __('Ingredients, items in preparation, and finished products.') }}</p>
        @if ($canManage)
            <div class="d-flex gap-2">
                <a href="{{ route('inventory-items.import') }}" class="btn btn-outline-primary">
                    <i class="bi bi-upload me-1"></i>{{ __('Import CSV') }}
                </a>
                <a href="{{ route('inventory-items.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>{{ __('Add Item') }}
                </a>
            </div>
        @endif
    </div>

    <form method="GET" action="{{ route('inventory-items.index') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
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
                <div class="col-6 col-md-2">
                    <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">{{ $canManage ? __('All') : __('Active') }}</option>
                        @if ($canManage)
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Active') }}</option>
                        @endif
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
                    <a href="{{ route('inventory-items.index') }}" class="btn btn-outline-secondary" title="{{ __('Clear filters') }}"><i class="bi bi-x-lg"></i></a>
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
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Unit') }}</th>
                        @if ($canManage)
                            <th class="text-end">{{ __('Unit Cost') }}</th>
                        @endif
                        <th class="text-end">{{ __('Reorder Level') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td class="text-nowrap"><code>{{ $item->item_code }}</code></td>
                            <td>
                                <a href="{{ route('inventory-items.show', $item) }}" class="fw-semibold text-decoration-none">{{ $item->name }}</a>
                            </td>
                            <td>{{ $item->category->name }}</td>
                            <td><x-item-type-badge :type="$item->item_type" /></td>
                            <td>{{ $item->unit_of_measure }}</td>
                            @if ($canManage)
                                <td class="text-end">₱{{ number_format($item->unit_cost, 2) }}</td>
                            @endif
                            <td class="text-end"><x-qty :value="$item->reorder_level" /></td>
                            <td>
                                @if ($item->status === 'active')
                                    <span class="badge text-bg-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 8 : 7 }}" class="text-center text-muted py-4">
                                {{ __('No items found.') }}
                                @if ($canManage && empty(array_filter($filters)))
                                    <a href="{{ route('inventory-items.create') }}">{{ __('Add the first item') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="card-footer bg-white">{{ $items->links() }}</div>
        @endif
    </div>
</x-app-layout>
