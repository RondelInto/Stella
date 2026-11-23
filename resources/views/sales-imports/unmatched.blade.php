<x-app-layout>
    <x-slot name="title">{{ __('Unmatched Products') }}</x-slot>
    <x-slot name="header">{{ __('Unmatched POS Products') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('sales-imports.index') }}">{{ __('POS Sales Imports') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('Unmatched Products') }}</li>
        </ol>
    </nav>

    <p class="text-muted">
        {{ __('These product names from POS files did not match any product in the system. Link each one to a product so sales analysis can use it.') }}
        {{ __('To match automatically next time, make the product\'s POS code or name the same as in the POS file (Catalog > Products).') }}
    </p>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Name in POS File') }}</th>
                        <th class="text-end">{{ __('Rows') }}</th>
                        <th class="text-end">{{ __('Quantity') }}</th>
                        <th>{{ __('Sold Between') }}</th>
                        <th style="min-width: 320px">{{ __('Link to Product') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($groups as $group)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $group->pos_product_name }}</span>
                                @if ($group->pos_product_code)
                                    <div class="small text-muted">{{ __('Code') }}: <code>{{ $group->pos_product_code }}</code></div>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($group->lines_count) }}</td>
                            <td class="text-end"><x-qty :value="$group->quantity" /></td>
                            <td class="small text-nowrap">
                                {{ \Illuminate\Support\Carbon::parse($group->first_sale)->format('M d, Y') }} –
                                {{ \Illuminate\Support\Carbon::parse($group->last_sale)->format('M d, Y') }}
                            </td>
                            <td>
                                <form method="POST" action="{{ route('sales-imports.link') }}" class="d-flex gap-2">
                                    @csrf
                                    <input type="hidden" name="pos_product_name" value="{{ $group->pos_product_name }}">
                                    <input type="hidden" name="pos_product_code" value="{{ $group->pos_product_code }}">
                                    <select name="product_id" class="form-select form-select-sm" required aria-label="{{ __('Product') }}">
                                        <option value="">{{ __('Choose a product…') }}</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">
                                                {{ $product->pos_product_name }}@if ($product->pos_product_code) ({{ $product->pos_product_code }})@endif
                                                @if ($product->status !== 'active') — {{ __('inactive') }}@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-link-45deg"></i> {{ __('Link') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">
                            <i class="bi bi-check-circle text-success me-1"></i>{{ __('Every imported sales row is linked to a product.') }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($groups->hasPages())
            <div class="card-footer bg-white">{{ $groups->links() }}</div>
        @endif
    </div>
    @if ($products->isEmpty() && $groups->isNotEmpty())
        <p class="small text-danger mt-2">{{ __('There are no products yet. Add them first under Catalog > Products.') }}</p>
    @endif
</x-app-layout>
