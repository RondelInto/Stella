{{-- Extra filters for the Inventory Movement report (rendered inside reports._header). --}}
<div class="col-12 col-md-2">
    <label for="location" class="form-label small mb-1">{{ __('Location') }}</label>
    <select id="location" name="location" class="form-select" @disabled($locations->count() === 1)>
        @foreach ($locations as $loc)
            <option value="{{ $loc->id }}" @selected($location->id === $loc->id)>{{ $loc->name }}</option>
        @endforeach
    </select>
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
    <label for="search" class="form-label small mb-1">{{ __('Item') }}</label>
    <input type="search" id="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Name or code') }}">
</div>
