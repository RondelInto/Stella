<x-app-layout>
    <x-slot name="title">{{ __('Products') }}</x-slot>
    <x-slot name="header">{{ __('Products') }}</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">{{ __('Food products sold to customers. Each is linked to a finished-product inventory item.') }}</p>
        <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Add Product') }}</a>
    </div>

    <form method="GET" action="{{ route('products.index') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                    <input type="search" id="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}"
                           placeholder="{{ __('Product name or POS code') }}">
                </div>
                <div class="col-6 col-md-3">
                    <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
                    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary" title="{{ __('Clear filters') }}"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('POS Code') }}</th>
                        <th>{{ __('Linked Inventory Item') }}</th>
                        <th class="text-end">{{ __('Selling Price') }}</th>
                        <th class="text-end">{{ __('Unit Cost') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td class="fw-semibold">{{ $product->pos_product_name }}</td>
                            <td>{{ $product->pos_product_code ?: '—' }}</td>
                            <td>
                                @if ($product->inventoryItem)
                                    <a href="{{ route('inventory-items.show', $product->inventoryItem) }}" class="text-decoration-none">
                                        <code>{{ $product->inventoryItem->item_code }}</code> {{ $product->inventoryItem->name }}
                                    </a>
                                @else
                                    <span class="text-danger small">{{ __('Not linked') }}</span>
                                @endif
                            </td>
                            <td class="text-end">₱{{ number_format($product->selling_price, 2) }}</td>
                            <td class="text-end text-muted">
                                {{ $product->inventoryItem ? '₱'.number_format($product->inventoryItem->unit_cost, 2) : '—' }}
                            </td>
                            <td>
                                @if ($product->status === 'active')
                                    <span class="badge text-bg-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('products.status', $product) }}" class="d-inline"
                                      @if ($product->status === 'active') data-confirm="{{ __('Deactivate :name?', ['name' => $product->pos_product_name]) }}" @endif>
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $product->status === 'active' ? 'inactive' : 'active' }}">
                                    <button type="submit" class="btn btn-sm {{ $product->status === 'active' ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                            title="{{ $product->status === 'active' ? __('Deactivate') : __('Reactivate') }}">
                                        <i class="bi {{ $product->status === 'active' ? 'bi-slash-circle' : 'bi-check-circle' }}"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No products yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($products->hasPages())
            <div class="card-footer bg-white">{{ $products->links() }}</div>
        @endif
    </div>
    <p class="small text-muted mt-2">{{ __('Unit cost comes from the linked inventory item and is used for profitability analysis.') }}</p>
</x-app-layout>
