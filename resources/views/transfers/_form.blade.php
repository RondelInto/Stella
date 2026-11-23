{{--
    Shared transfer form (create + edit).
    Variables: $source, $branches, $items, $stock (commissary qty per item id), $maxItems, $transfer (edit only).
--}}
@php
    $transfer = $transfer ?? null;

    // Rows to show: old input after a validation error, otherwise the saved lines when editing.
    $lines = old('lines', $transfer?->details->map(fn ($d) => [
        'inventory_item_id' => $d->inventory_item_id,
        'quantity' => rtrim(rtrim($d->quantity_sent, '0'), '.'),
    ])->all() ?? []);

    $itemsById = $items->keyBy('id');
@endphp

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <x-input-label :value="__('From')" />
        <input type="text" class="form-control" value="{{ $source->name }}" disabled>
    </div>
    <div class="col-md-4">
        <x-input-label for="destination_location_id" :value="__('To (Branch)')" />
        <select id="destination_location_id" name="destination_location_id"
                class="form-select @error('destination_location_id') is-invalid @enderror" required>
            <option value="">{{ __('â€” Select branch â€”') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((int) old('destination_location_id', $transfer?->destination_location_id) === $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('destination_location_id')" />
    </div>
    <div class="col-md-4">
        <x-input-label for="transfer_date" :value="__('Transfer Date')" />
        <input type="date" id="transfer_date" name="transfer_date" max="{{ today()->toDateString() }}"
               class="form-control @error('transfer_date') is-invalid @enderror"
               value="{{ old('transfer_date', $transfer?->transfer_date?->toDateString() ?? today()->toDateString()) }}" required>
        <x-input-error :messages="$errors->get('transfer_date')" />
    </div>
</div>

{{-- Item picker --}}
<h2 class="h6">{{ __('Items') }}</h2>
<div class="border rounded p-3 bg-light mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-7">
            <label for="picker_item" class="form-label small mb-1">{{ __('Item (commissary stock shown in brackets)') }}</label>
            <input type="search" id="picker_filter" class="form-control form-control-sm mb-1" placeholder="{{ __('Type to filterâ€¦') }}" autocomplete="off">
            <select id="picker_item" class="form-select" size="5">
                @foreach ($items as $item)
                    @php
                        $onHand = rtrim(rtrim(number_format((float) ($stock[$item->id] ?? 0), 3), '0'), '.');
                    @endphp
                    <option value="{{ $item->id }}" data-label="{{ $item->item_code }} â€” {{ $item->name }}"
                            data-unit="{{ $item->unit_of_measure }}" data-stock="{{ $onHand }}">
                        {{ $item->item_code }} â€” {{ $item->name }} [{{ $onHand }} {{ $item->unit_of_measure }}]
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="picker_qty" class="form-label small mb-1">{{ __('Quantity') }}</label>
            <input type="number" id="picker_qty" class="form-control" step="0.001" min="0.001">
        </div>
        <div class="col-6 col-md-2">
            <button type="button" id="picker_add" class="btn btn-outline-primary w-100"><i class="bi bi-plus-lg"></i> {{ __('Add') }}</button>
        </div>
    </div>
    <div id="picker_error" class="small text-danger mt-1"></div>
</div>

@if ($errors->has('lines') || $errors->has('lines.*'))
    <div class="alert alert-danger small py-2">
        @foreach (collect($errors->get('lines'))->merge(collect($errors->get('lines.*'))->flatten())->unique() as $message)
            <div>{{ $message }}</div>
        @endforeach
    </div>
@endif

<div class="table-responsive">
    <table class="table align-middle" id="lines_table">
        <thead class="table-light">
            <tr>
                <th>{{ __('Item') }}</th>
                <th class="text-end">{{ __('Commissary Stock') }}</th>
                <th style="width: 200px">{{ __('Quantity to Send') }}</th>
                <th style="width: 50px"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $line)
                @php
                    $lineItem = $itemsById->get((int) $line['inventory_item_id']);
                @endphp
                @continue(! $lineItem)
                <tr data-item-id="{{ $lineItem->id }}">
                    <td>
                        {{ $lineItem->item_code }} â€” {{ $lineItem->name }}
                        <input type="hidden" name="lines[{{ $i }}][inventory_item_id]" value="{{ $lineItem->id }}">
                    </td>
                    <td class="text-end text-muted"><x-qty :value="$stock[$lineItem->id] ?? 0" :unit="$lineItem->unit_of_measure" /></td>
                    <td>
                        <div class="input-group input-group-sm">
                            <input type="number" name="lines[{{ $i }}][quantity]" value="{{ $line['quantity'] }}" step="0.001" min="0.001"
                                   class="form-control @error('lines.'.$i.'.quantity') is-invalid @enderror" required>
                            <span class="input-group-text">{{ $lineItem->unit_of_measure }}</span>
                        </div>
                    </td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger js-remove" title="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></td>
                </tr>
            @endforeach
            <tr id="no_lines" @if (count($lines)) hidden @endif>
                <td colspan="4" class="text-center text-muted py-3">{{ __('No items added yet.') }}</td>
            </tr>
        </tbody>
    </table>
</div>
<p class="small text-muted">{{ __('Maximum :max items per transfer. Stock is only deducted when the transfer is released.', ['max' => $maxItems]) }}</p>

<div class="mt-3">
    <x-input-label for="remarks" :value="__('Remarks (optional)')" />
    <input type="text" id="remarks" name="remarks" maxlength="500" class="form-control" value="{{ old('remarks', $transfer?->remarks) }}">
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const filter = document.getElementById('picker_filter');
        const picker = document.getElementById('picker_item');
        const qty = document.getElementById('picker_qty');
        const addBtn = document.getElementById('picker_add');
        const error = document.getElementById('picker_error');
        const tbody = document.querySelector('#lines_table tbody');
        const emptyRow = document.getElementById('no_lines');
        const maxItems = {{ (int) $maxItems }};
        let nextIndex = {{ count($lines) + 1000 }}; // unique array keys for new rows

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
            if (!option) { error.textContent = @js(__('Select an item first.')); return; }
            if (!(parseFloat(qty.value) > 0)) { error.textContent = @js(__('Enter a quantity greater than zero.')); return; }
            if (tbody.querySelector(`tr[data-item-id="${option.value}"]`)) { error.textContent = @js(__('This item is already in the list. Change its quantity below.')); return; }
            if (rowCount() >= maxItems) { error.textContent = @js(__('Maximum number of items reached.')); return; }

            const i = nextIndex++;
            const tr = document.createElement('tr');
            tr.dataset.itemId = option.value;
            tr.innerHTML = `
                <td></td>
                <td class="text-end text-muted"></td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" name="lines[${i}][quantity]" step="0.001" min="0.001" class="form-control" required>
                        <span class="input-group-text"></span>
                    </div>
                </td>
                <td><button type="button" class="btn btn-sm btn-outline-danger js-remove" title="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></td>`;
            // textContent (not innerHTML) for item data, so names can never inject HTML.
            tr.cells[0].textContent = option.dataset.label;
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `lines[${i}][inventory_item_id]`;
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
