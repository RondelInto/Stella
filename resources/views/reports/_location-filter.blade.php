{{-- "All locations / one location" filter for reports (rendered inside reports._header). --}}
<div class="col-12 col-md-3">
    <label for="location" class="form-label small mb-1">{{ __('Location') }}</label>
    <select id="location" name="location" class="form-select">
        <option value="">{{ __('All') }}</option>
        @foreach ($locations as $loc)
            <option value="{{ $loc->id }}" @selected($selectedLocation === $loc->id)>{{ $loc->name }}</option>
        @endforeach
    </select>
</div>
