<x-app-layout>
    <x-slot name="title">{{ __('Start Stock Count') }}</x-slot>
    <x-slot name="header">{{ __('Start Stock Count') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('stock-counts.index') }}">{{ __('Stock Counts') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('Start') }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <form method="POST" action="{{ route('stock-counts.store') }}" class="card border-0 shadow-sm">
                @csrf
                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <x-input-label :value="__('Location')" />
                            <input type="text" class="form-control" value="{{ $location->name }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <x-input-label for="count_date" :value="__('Count Date')" />
                            <input type="date" id="count_date" name="count_date" max="{{ today()->toDateString() }}"
                                   class="form-control @error('count_date') is-invalid @enderror"
                                   value="{{ old('count_date', today()->toDateString()) }}" required>
                            <x-input-error :messages="$errors->get('count_date')" />
                        </div>
                    </div>

                    <h2 class="h6">{{ __('What will you count?') }}</h2>
                    <p class="small text-muted">{{ __('Choose whole categories, specific items, or both. Maximum :max items per count.', ['max' => $maxItems]) }}</p>
                    <x-input-error :messages="$errors->get('items')" class="mb-2" />

                    <div class="row g-4">
                        <div class="col-md-5">
                            <span class="form-label d-block">{{ __('Whole categories') }}</span>
                            @foreach ($categories as $category)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="categories[]" value="{{ $category->id }}"
                                           id="cat_{{ $category->id }}" @checked(in_array($category->id, old('categories', [])))
                                           @disabled($category->active_items_count === 0)>
                                    <label class="form-check-label" for="cat_{{ $category->id }}">
                                        {{ $category->name }} <span class="text-muted small">({{ $category->active_items_count }})</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <div class="col-md-7">
                            <label for="items" class="form-label">{{ __('Specific items') }}</label>
                            <input type="search" class="form-control form-control-sm mb-1" id="item_filter"
                                   placeholder="{{ __('Type to filter…') }}" autocomplete="off">
                            <select id="items" name="items[]" multiple size="8" class="form-select">
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}" @selected(in_array($item->id, old('items', [])))>
                                        {{ $item->item_code }} — {{ $item->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ __('Hold Ctrl (or Cmd on Mac) to select several items.') }}</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-input-label for="remarks" :value="__('Remarks (optional)')" />
                        <input type="text" id="remarks" name="remarks" maxlength="500" class="form-control"
                               value="{{ old('remarks') }}" placeholder="{{ __('e.g. Closing count, Opening balance') }}">
                    </div>
                </div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('stock-counts.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <x-primary-button><i class="bi bi-clipboard-check me-1"></i>{{ __('Start Count') }}</x-primary-button>
                </div>
            </form>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body small">
                    <h2 class="h6">{{ __('How counting works') }}</h2>
                    <ol class="mb-3 ps-3">
                        <li>{{ __('Start the count and choose the items.') }}</li>
                        <li>{{ __('Physically count each item and enter the quantity. You can save and continue later.') }}</li>
                        <li>{{ __('Complete the count. The system compares your numbers with its records.') }}</li>
                        <li>{{ __('Differences are corrected automatically and listed as discrepancies.') }}</li>
                    </ol>
                    <p class="mb-0 text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        {{ __('First time using the system? Your first count sets the opening stock balances.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const filter = document.getElementById('item_filter');
            const select = document.getElementById('items');
            filter.addEventListener('input', () => {
                const term = filter.value.toLowerCase();
                for (const option of select.options) {
                    option.hidden = term !== '' && !option.text.toLowerCase().includes(term);
                }
            });
        });
    </script>
</x-app-layout>
