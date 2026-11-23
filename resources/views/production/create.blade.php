<x-app-layout>
    <x-slot name="title">{{ __('Record Production') }}</x-slot>
    <x-slot name="header">{{ __('Record Production') }}</x-slot>

    @php
        $lines = old('ingredients', []);
        $ingredientsById = $ingredients->keyBy('id');
    @endphp

    <form method="POST" action="{{ route('production.store') }}" class="card border-0 shadow-sm">
        @csrf
        <div class="card-body p-4">
            <div class="row g-3 mb-4">
                <div class="col-md-5">
                    <x-input-label for="product_id" :value="__('Product Made')" />
                    <select id="product_id" name="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                        <option value="">{{ __('— Select product —') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" data-unit="{{ $product->inventoryItem->unit_of_measure }}"
                                    @selected((int) old('product_id') === $product->id)>{{ $product->pos_product_name }}</option>
                        @endforeach
                    </select>
                    @if ($products->isEmpty())
                        <div class="form-text text-danger">{{ __('No products yet. Management must add products first.') }}</div>
                    @endif
                    <x-input-error :messages="$errors->get('product_id')" />
                </div>
                <div class="col-md-3">
                    <x-input-label for="quantity_produced" :value="__('Quantity Made')" />
                    <div class="input-group">
                        <input type="number" id="quantity_produced" name="quantity_produced" step="0.001" min="0.001"
                               class="form-control @error('quantity_produced') is-invalid @enderror" value="{{ old('quantity_produced') }}" required>
                        <span class="input-group-text" id="product_unit">{{ __('unit') }}</span>
                    </div>
                    <x-input-error :messages="$errors->get('quantity_produced')" />
                </div>
                <div class="col-md-4">
                    <x-input-label for="production_date" :value="__('Production Date')" />
                    <input type="date" id="production_date" name="production_date" max="{{ today()->toDateString() }}"
                           class="form-control @error('production_date') is-invalid @enderror"
                           value="{{ old('production_date', today()->toDateString()) }}" required>
                    <x-input-error :messages="$errors->get('production_date')" />
                </div>
            </div>

            <h2 class="h6">{{ __('Ingredients Used') }}</h2>
            <p class="small text-muted">{{ __('Enter what was actually used for this batch. Commissary stock is shown in brackets.') }}</p>

            <div class="border rounded p-3 bg-light mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-7">
                        <label for="picker_item" class="form-label small mb-1">{{ __('Ingredient') }}</label>
                        <input type="search" id="picker_filter" class="form-control form-control-sm mb-1" placeholder="{{ __('Type to filter…') }}" autocomplete="off">
                        <select id="picker_item" class="form-select" size="5">
                            @foreach ($ingredients as $item)
                                @php
                                    $onHand = rtrim(rtrim(number_format((float) ($stock[$item->id] ?? 0), 3), '0'), '.');
                                @endphp
                                <option value="{{ $item->id }}" data-label="{{ $item->item_code }} — {{ $item->name }}"
                                        data-unit="{{ $item->unit_of_measure }}" data-stock="{{ $onHand }}">
                                    {{ $item->item_code }} — {{ $item->name }} [{{ $onHand }} {{ $item->unit_of_measure }}]
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="picker_qty" class="form-label small mb-1">{{ __('Quantity Used') }}</label>
                        <input type="number" id="picker_qty" class="form-control" step="0.001" min="0.001">
                    </div>
                    <div class="col-6 col-md-2">
                        <button type="button" id="picker_add" class="btn btn-outline-primary w-100"><i class="bi bi-plus-lg"></i> {{ __('Add') }}</button>
                    </div>
                </div>
                <div id="picker_error" class="small text-danger mt-1"></div>
            </div>

            @if ($errors->has('ingredients') || $errors->has('ingredients.*'))
                <div class="alert alert-danger small py-2">
                    @foreach (collect($errors->get('ingredients'))->merge(collect($errors->get('ingredients.*'))->flatten())->unique() as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                </div>
            @endif

            <div class="table-responsive">
                <table class="table align-middle" id="lines_table">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Ingredient') }}</th>
                            <th class="text-end">{{ __('Commissary Stock') }}</th>
                            <th style="width: 200px">{{ __('Quantity Used') }}</th>
                            <th style="width: 50px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $i => $line)
                            @php
                                $lineItem = $ingredientsById->get((int) ($line['inventory_item_id'] ?? 0));
                            @endphp
                            @continue(! $lineItem)
                            <tr data-item-id="{{ $lineItem->id }}">
                                <td>
                                    {{ $lineItem->item_code }} — {{ $lineItem->name }}
                                    <input type="hidden" name="ingredients[{{ $i }}][inventory_item_id]" value="{{ $lineItem->id }}">
                                </td>
                                <td class="text-end text-muted"><x-qty :value="$stock[$lineItem->id] ?? 0" :unit="$lineItem->unit_of_measure" /></td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="ingredients[{{ $i }}][quantity]" value="{{ $line['quantity'] ?? '' }}" step="0.001" min="0.001"
                                               class="form-control @error('ingredients.'.$i.'.quantity') is-invalid @enderror" required>
                                        <span class="input-group-text">{{ $lineItem->unit_of_measure }}</span>
                                    </div>
                                </td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger js-remove" title="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                        @endforeach
                        <tr id="no_lines" @if (count($lines)) hidden @endif>
                            <td colspan="4" class="text-center text-muted py-3">{{ __('No ingredients added yet.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                <x-input-label for="remarks" :value="__('Remarks (optional)')" />
                <input type="text" id="remarks" name="remarks" maxlength="500" class="form-control" value="{{ old('remarks') }}">
            </div>

            <div class="alert alert-light border small mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                {{ __('Saving deducts the ingredients and adds the finished product to commissary stock. Records cannot be edited; mistakes are corrected with a stock count.') }}
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('production.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <x-primary-button><i class="bi bi-check-lg me-1"></i>{{ __('Save Production') }}</x-primary-button>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const product = document.getElementById('product_id');
            const productUnit = document.getElementById('product_unit');
            const filter = document.getElementById('picker_filter');
            const picker = document.getElementById('picker_item');
            const qty = document.getElementById('picker_qty');
            const addBtn = document.getElementById('picker_add');
            const error = document.getElementById('picker_error');
            const tbody = document.querySelector('#lines_table tbody');
            const emptyRow = document.getElementById('no_lines');
            const maxLines = {{ (int) $maxIngredients }};
            let nextIndex = {{ count($lines) + 1000 }};

            const showProductUnit = () => {
                const option = product.options[product.selectedIndex];
                productUnit.textContent = option && option.dataset.unit ? option.dataset.unit : @js(__('unit'));
            };
            product.addEventListener('change', showProductUnit);
            showProductUnit();

            const rowCount = () => tbody.querySelectorAll('tr[data-item-id]').length;
            const refreshEmpty = () => { emptyRow.hidden = rowCount() > 0; };

            filter.addEventListener('input', () => {
                const term = filter.value.toLowerCase();
                for (const option of picker.options) {
                    option.hidden = term !== '' && !option.text.toLowerCase().includes(term);
                }
            });

            addBtn.addEventListener('click', () => {
                error.textContent = '';
                const option = picker.options[picker.selectedIndex];
                if (!option) { error.textContent = @js(__('Select an ingredient first.')); return; }
                if (!(parseFloat(qty.value) > 0)) { error.textContent = @js(__('Enter a quantity greater than zero.')); return; }
                if (tbody.querySelector(`tr[data-item-id="${option.value}"]`)) { error.textContent = @js(__('This ingredient is already listed. Change its quantity below.')); return; }
                if (rowCount() >= maxLines) { error.textContent = @js(__('Maximum number of ingredients reached.')); return; }

                const i = nextIndex++;
                const tr = document.createElement('tr');
                tr.dataset.itemId = option.value;
                tr.innerHTML = `
                    <td></td>
                    <td class="text-end text-muted"></td>
                    <td>
                        <div class="input-group input-group-sm">
                            <input type="number" name="ingredients[${i}][quantity]" step="0.001" min="0.001" class="form-control" required>
                            <span class="input-group-text"></span>
                        </div>
                    </td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger js-remove"><i class="bi bi-x-lg"></i></button></td>`;
                tr.cells[0].textContent = option.dataset.label;
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = `ingredients[${i}][inventory_item_id]`;
                hidden.value = option.value;
                tr.cells[0].appendChild(hidden);
                tr.cells[1].textContent = `${option.dataset.stock} ${option.dataset.unit}`;
                tr.querySelector('input[type=number]').value = qty.value;
                tr.querySelector('.input-group-text').textContent = option.dataset.unit;

                tbody.insertBefore(tr, emptyRow);
                qty.value = '';
                refreshEmpty();
            });

            tbody.addEventListener('click', (event) => {
                const button = event.target.closest('.js-remove');
                if (button) { button.closest('tr').remove(); refreshEmpty(); }
            });
        });
    </script>
</x-app-layout>
