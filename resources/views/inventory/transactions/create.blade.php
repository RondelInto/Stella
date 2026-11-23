<x-app-layout>
    <x-slot name="title">{{ __('Record Transaction') }}</x-slot>
    <x-slot name="header">{{ __('Record Transaction') }}</x-slot>

    <div class="row g-4">
        <div class="col-12 col-xl-7">
            <form method="POST" action="{{ route('inventory-transactions.store') }}" class="card border-0 shadow-sm">
                @csrf
                <div class="card-body p-4">
                    {{-- Type --}}
                    <span class="form-label d-block">{{ __('Type') }}</span>
                    <div class="d-flex flex-wrap gap-2 mb-1" role="group" aria-label="{{ __('Transaction type') }}">
                        @foreach ($types as $value => $label)
                            <input type="radio" class="btn-check" name="transaction_type" id="type_{{ $value }}" value="{{ $value }}"
                                   @checked(old('transaction_type', 'received') === $value) required>
                            <label class="btn btn-outline-secondary" for="type_{{ $value }}">{{ __($label) }}</label>
                        @endforeach
                    </div>
                    <div class="form-text mb-3">
                        {{ __('Received adds stock. Issued and Damaged / Spoiled deduct stock.') }}
                    </div>
                    <x-input-error :messages="$errors->get('transaction_type')" class="mb-3" />

                    {{-- Location --}}
                    <div class="mb-3">
                        <x-input-label for="location_id" :value="__('Location')" />
                        @if ($locations->count() === 1)
                            <input type="text" class="form-control" value="{{ $locations->first()->name }}" disabled>
                            <input type="hidden" name="location_id" value="{{ $locations->first()->id }}">
                        @else
                            <select id="location_id" name="location_id" class="form-select @error('location_id') is-invalid @enderror" required>
                                <option value="">{{ __('— Select location —') }}</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected((int) old('location_id') === $location->id)>{{ $location->name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <x-input-error :messages="$errors->get('location_id')" />
                    </div>

                    {{-- Item (with a quick filter box for long lists) --}}
                    <div class="mb-3">
                        <x-input-label for="inventory_item_id" :value="__('Item')" />
                        <input type="search" class="form-control form-control-sm mb-1" id="item_filter"
                               placeholder="{{ __('Type to filter items by name or code…') }}" autocomplete="off">
                        <select id="inventory_item_id" name="inventory_item_id" size="6"
                                class="form-select @error('inventory_item_id') is-invalid @enderror" required>
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}" data-unit="{{ $item->unit_of_measure }}"
                                        @selected((int) old('inventory_item_id', $selectedItemId) === $item->id)>
                                    {{ $item->item_code }} — {{ $item->name }} ({{ $item->unit_of_measure }})
                                </option>
                            @endforeach
                        </select>
                        @if ($items->isEmpty())
                            <div class="form-text text-danger">{{ __('No active items yet. Management must add items first.') }}</div>
                        @endif
                        <x-input-error :messages="$errors->get('inventory_item_id')" />
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <x-input-label for="quantity" :value="__('Quantity')" />
                            <div class="input-group">
                                <input type="number" id="quantity" name="quantity" step="0.001" min="0.001"
                                       class="form-control @error('quantity') is-invalid @enderror"
                                       value="{{ old('quantity') }}" required>
                                <span class="input-group-text" id="unit_label">{{ __('unit') }}</span>
                            </div>
                            <x-input-error :messages="$errors->get('quantity')" />
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="transaction_date" :value="__('Date and Time')" />
                            <input type="datetime-local" id="transaction_date" name="transaction_date"
                                   class="form-control @error('transaction_date') is-invalid @enderror"
                                   value="{{ old('transaction_date', now()->format('Y-m-d\TH:i')) }}"
                                   max="{{ now()->format('Y-m-d\TH:i') }}" required>
                            <x-input-error :messages="$errors->get('transaction_date')" />
                        </div>
                    </div>

                    <div class="mb-0">
                        <x-input-label for="remarks" :value="__('Remarks (optional)')" />
                        <textarea id="remarks" name="remarks" rows="2" maxlength="500"
                                  class="form-control @error('remarks') is-invalid @enderror"
                                  placeholder="{{ __('e.g. supplier, receipt number, reason for damage') }}">{{ old('remarks') }}</textarea>
                        <x-input-error :messages="$errors->get('remarks')" />
                    </div>
                </div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('stock-levels.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <x-primary-button><i class="bi bi-check-lg me-1"></i>{{ __('Save Transaction') }}</x-primary-button>
                </div>
            </form>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body small">
                    <h2 class="h6">{{ __('Important') }}</h2>
                    <ul class="mb-0">
                        <li>{{ __('Saved transactions cannot be edited or deleted. They form the audit trail.') }}</li>
                        <li>{{ __('If you make a mistake, it is corrected during the next stock count.') }}</li>
                        <li>{{ __('You cannot issue more than the stock on hand.') }}</li>
                        <li>{{ __('Stock transfers and production are recorded in their own pages.') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Small helper: filter the item list as you type, and show the selected item's unit.
        document.addEventListener('DOMContentLoaded', () => {
            const filter = document.getElementById('item_filter');
            const select = document.getElementById('inventory_item_id');
            const unit = document.getElementById('unit_label');

            const showUnit = () => {
                const option = select.options[select.selectedIndex];
                unit.textContent = option ? option.dataset.unit : 'unit';
            };

            filter.addEventListener('input', () => {
                const term = filter.value.toLowerCase();
                for (const option of select.options) {
                    option.hidden = term !== '' && !option.text.toLowerCase().includes(term);
                }
            });

            select.addEventListener('change', showUnit);
            showUnit();
        });
    </script>
</x-app-layout>
