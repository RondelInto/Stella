{{-- Shared product form. Variables: $items (linkable finished-product items), $product (edit only). --}}
@php
    $product = $product ?? null;
@endphp

<div class="row g-3">
    <div class="col-md-8">
        <x-input-label for="pos_product_name" :value="__('Product Name')" />
        <x-text-input id="pos_product_name" name="pos_product_name" :value="old('pos_product_name', $product?->pos_product_name)"
                      :invalid="$errors->has('pos_product_name')" required />
        <div class="form-text">{{ __('Use the same name as in the POS if possible.') }}</div>
        <x-input-error :messages="$errors->get('pos_product_name')" />
    </div>
    <div class="col-md-4">
        <x-input-label for="pos_product_code" :value="__('POS Product Code (optional)')" />
        <x-text-input id="pos_product_code" name="pos_product_code" :value="old('pos_product_code', $product?->pos_product_code)"
                      :invalid="$errors->has('pos_product_code')" />
        <div class="form-text">{{ __('Used to match imported POS sales.') }}</div>
        <x-input-error :messages="$errors->get('pos_product_code')" />
    </div>
    <div class="col-md-8">
        <x-input-label for="inventory_item_id" :value="__('Linked Inventory Item (Finished Product)')" />
        <select id="inventory_item_id" name="inventory_item_id" class="form-select @error('inventory_item_id') is-invalid @enderror" required>
            <option value="">{{ __('— Select —') }}</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}" @selected((int) old('inventory_item_id', $product?->inventory_item_id) === $item->id)>
                    {{ $item->item_code }} — {{ $item->name }} ({{ $item->unit_of_measure }}){{ $item->status !== 'active' ? ' — inactive' : '' }}
                </option>
            @endforeach
        </select>
        <div class="form-text">
            {{ __('Production adds stock to this item, and its unit cost is the product cost.') }}
            @if ($items->isEmpty())
                <span class="text-danger">{{ __('No unlinked finished-product items. Add one under Inventory > Items first.') }}</span>
            @endif
        </div>
        <x-input-error :messages="$errors->get('inventory_item_id')" />
    </div>
    <div class="col-md-4">
        <x-input-label for="selling_price" :value="__('Selling Price (₱)')" />
        <x-text-input id="selling_price" name="selling_price" type="number" step="0.01" min="0"
                      :value="old('selling_price', $product?->selling_price ?? '0.00')" :invalid="$errors->has('selling_price')" required />
        <x-input-error :messages="$errors->get('selling_price')" />
    </div>
</div>
