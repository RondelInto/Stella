<x-app-layout>
    <x-slot name="title">{{ __('Stock Count #:id', ['id' => $count->id]) }}</x-slot>
    <x-slot name="header">{{ __('Stock Count #:id', ['id' => $count->id]) }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('stock-counts.index') }}">{{ __('Stock Counts') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">#{{ $count->id }}</li>
        </ol>
    </nav>

    {{-- Summary --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap gap-4 small">
            <div><span class="text-muted">{{ __('Location') }}</span><div class="fw-semibold">{{ $count->location->name }}</div></div>
            <div><span class="text-muted">{{ __('Count Date') }}</span><div class="fw-semibold">{{ $count->count_date->format('M d, Y') }}</div></div>
            <div><span class="text-muted">{{ __('Started By') }}</span><div class="fw-semibold">{{ $count->countedBy->name }}</div></div>
            <div><span class="text-muted">{{ __('Items') }}</span><div class="fw-semibold">{{ $details->count() }}</div></div>
            <div>
                <span class="text-muted">{{ __('Status') }}</span>
                <div>
                    @if ($count->isDraft())
                        <span class="badge text-bg-warning">{{ __('In progress') }}</span>
                    @else
                        <span class="badge text-bg-success">{{ __('Completed') }}</span>
                        <span class="text-muted">{{ $count->completed_at?->format('M d, Y h:i A') }}</span>
                    @endif
                </div>
            </div>
            @if ($count->remarks)
                <div><span class="text-muted">{{ __('Remarks') }}</span><div>{{ $count->remarks }}</div></div>
            @endif
        </div>
    </div>

    @if ($count->isDraft() && $canEdit)
        {{-- ============ BLIND ENTRY FORM (no system quantities shown) ============ --}}
        @php($entered = $details->whereNotNull('counted_quantity')->count())
        <form method="POST" action="{{ route('stock-counts.update', $count) }}" id="count-form">
            @csrf
            @method('PUT')

            <div class="alert alert-light border small d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span>
                    <i class="bi bi-eye-slash me-1"></i>
                    {{ __('Blind count: enter what you physically count. Use 0 if there is none.') }}
                </span>
                <span class="fw-semibold">{{ __(':done of :total entered', ['done' => $entered, 'total' => $details->count()]) }}</span>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger small">
                    @foreach (collect($errors->all())->unique() as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                </div>
            @endif

            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Code') }}</th>
                                <th>{{ __('Item') }}</th>
                                <th>{{ __('Category') }}</th>
                                <th style="width: 220px">{{ __('Counted Quantity') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($details as $detail)
                                <tr>
                                    <td class="text-nowrap"><code>{{ $detail->inventoryItem->item_code }}</code></td>
                                    <td>{{ $detail->inventoryItem->name }}</td>
                                    <td class="small text-muted">{{ $detail->inventoryItem->category->name }}</td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.001" min="0" inputmode="decimal"
                                                   name="counts[{{ $detail->id }}]"
                                                   value="{{ old('counts.'.$detail->id, $detail->counted_quantity !== null ? rtrim(rtrim($detail->counted_quantity, '0'), '.') : '') }}"
                                                   class="form-control @error('counts.'.$detail->id) is-invalid @enderror"
                                                   aria-label="{{ __('Counted quantity for :item', ['item' => $detail->inventoryItem->name]) }}">
                                            <span class="input-group-text">{{ $detail->inventoryItem->unit_of_measure }}</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white d-flex flex-wrap justify-content-between gap-2">
                    <button type="submit" form="delete-count" class="btn btn-outline-danger">
                        <i class="bi bi-trash me-1"></i>{{ __('Delete Draft') }}
                    </button>
                    <div class="d-flex gap-2">
                        <button type="submit" name="action" value="save" class="btn btn-outline-primary">
                            <i class="bi bi-save me-1"></i>{{ __('Save Progress') }}
                        </button>
                        <button type="submit" name="action" value="complete" class="btn btn-primary"
                                onclick="return confirm('{{ __('Complete this count? Stock will be corrected to your counted quantities. This cannot be undone.') }}')">
                            <i class="bi bi-check2-all me-1"></i>{{ __('Complete Count') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <form method="POST" action="{{ route('stock-counts.destroy', $count) }}" id="delete-count"
              data-confirm="{{ __('Delete this draft count? No stock has been changed.') }}">
            @csrf
            @method('DELETE')
        </form>

    @elseif ($count->isDraft())
        {{-- Draft viewed by someone who can't edit it (e.g. Management): no quantities shown. --}}
        <div class="alert alert-warning">
            <i class="bi bi-hourglass-split me-1"></i>
            {{ __('This count is still in progress at :location. Results appear once it is completed.', ['location' => $count->location->name]) }}
        </div>

    @else
        {{-- ============ RESULTS ============ --}}
        @php($discrepancies = $details->filter(fn ($d) => (float) $d->variance !== 0.0)->count())
        <div class="alert {{ $discrepancies ? 'alert-danger' : 'alert-success' }}">
            @if ($discrepancies)
                <i class="bi bi-exclamation-diamond me-1"></i>
                {{ __(':count item(s) did not match the system. Stock was corrected to the counted quantities.', ['count' => $discrepancies]) }}
            @else
                <i class="bi bi-check-circle me-1"></i>{{ __('All counted quantities matched the system.') }}
            @endif
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('Item') }}</th>
                            <th class="text-end">{{ __('System') }}</th>
                            <th class="text-end">{{ __('Counted') }}</th>
                            <th class="text-end">{{ __('Variance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($details as $detail)
                            <tr class="{{ (float) $detail->variance !== 0.0 ? 'table-warning' : '' }}">
                                <td class="text-nowrap"><code>{{ $detail->inventoryItem->item_code }}</code></td>
                                <td>
                                    <a href="{{ route('inventory-items.show', $detail->inventoryItem) }}" class="text-decoration-none">{{ $detail->inventoryItem->name }}</a>
                                </td>
                                <td class="text-end"><x-qty :value="$detail->system_quantity" /></td>
                                <td class="text-end"><x-qty :value="$detail->counted_quantity" /></td>
                                <td class="text-end text-nowrap"><x-variance :value="$detail->variance" :unit="$detail->inventoryItem->unit_of_measure" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <p class="small text-muted mt-2">
            {{ __('Variance = Counted − System. Negative (red) means stock was missing; positive (green) means extra stock was found.') }}
        </p>
    @endif
</x-app-layout>
