{{-- Shared item form. Variables: $categories, $types, and $item (only when editing). --}}
@php($item = $item ?? null)

<div class="row g-3">
    <div class="col-md-4">
        <x-input-label for="item_code" :value="__('Item Code')" />
        <x-text-input id="item_code" name="item_code" :value="old('item_code', $item?->item_code)"
                      :invalid="$errors->has('item_code')" required placeholder="{{ __('e.g. KAK-001') }}" />
        <div class="form-text">{{ __('Unique. Letters, numbers, dots, dashes. Saved in capitals.') }}</div>
        <x-input-error :messages="$errors->get('item_code')" />
    </div>
    <div class="col-md-8">
        <x-input-label for="name" :value="__('Item Name')" />
        <x-text-input id="name" name="name" :value="old('name', $item?->name)" :invalid="$errors->has('name')" required />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div class="col-md-6">
        <x-input-label for="inventory_category_id" :value="__('Category')" />
        <select id="inventory_category_id" name="inventory_category_id"
                class="form-select @error('inventory_category_id') is-invalid @enderror" required>
            <option value="">{{ __('— Select —') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) old('inventory_category_id', $item?->inventory_category_id) === $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('inventory_category_id')" />
    </div>
    <div class="col-md-6">
        <x-input-label for="item_type" :value="__('Type')" />
        <select id="item_type" name="item_type" class="form-select @error('item_type') is-invalid @enderror" required>
            <option value="">{{ __('— Select —') }}</option>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(old('item_type', $item?->item_type) === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
        <div class="form-text">{{ __('Finished products can later be linked to a sellable product.') }}</div>
        <x-input-error :messages="$errors->get('item_type')" />
    </div>

    <div class="col-md-4">
        <x-input-label for="unit_of_measure" :value="__('Unit')" />
        <x-text-input id="unit_of_measure" name="unit_of_measure" :value="old('unit_of_measure', $item?->unit_of_measure)"
                      :invalid="$errors->has('unit_of_measure')" required list="unit-suggestions"
                      placeholder="{{ __('e.g. kg, pcs, bilao') }}" />
        <datalist id="unit-suggestions">
            <option value="kg"><option value="g"><option value="liter"><option value="ml">
            <option value="pcs"><option value="pack"><option value="bilao"><option value="tray"><option value="bottle">
        </datalist>
        <x-input-error :messages="$errors->get('unit_of_measure')" />
    </div>
    <div class="col-md-4">
        <x-input-label for="unit_cost" :value="__('Unit Cost (₱)')" />
        <x-text-input id="unit_cost" name="unit_cost" type="number" step="0.01" min="0"
                      :value="old('unit_cost', $item?->unit_cost ?? '0.00')" :invalid="$errors->has('unit_cost')" required />
        <div class="form-text">{{ __('Cost per unit. Used for profitability analysis.') }}</div>
        <x-input-error :messages="$errors->get('unit_cost')" />
    </div>
    <div class="col-md-4">
        <x-input-label for="reorder_level" :value="__('Reorder Level')" />
        <x-text-input id="reorder_level" name="reorder_level" type="number" step="0.001" min="0"
                      :value="old('reorder_level', $item?->reorder_level ?? '0')" :invalid="$errors->has('reorder_level')" required />
        <div class="form-text">{{ __('Show a low-stock warning at or below this quantity.') }}</div>
        <x-input-error :messages="$errors->get('reorder_level')" />
    </div>
</div>
