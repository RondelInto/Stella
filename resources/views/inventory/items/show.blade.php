<x-app-layout>
    <x-slot name="title">{{ $item->name }}</x-slot>
    <x-slot name="header">{{ __('Item Details') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('inventory-items.index') }}">{{ __('Items') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $item->name }}</li>
        </ol>
    </nav>

    {{-- Item summary --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <h2 class="h4 mb-1">{{ $item->name }}</h2>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <code>{{ $item->item_code }}</code>
                        <x-item-type-badge :type="$item->item_type" />
                        <span class="badge text-bg-light border fw-normal">{{ $item->category->name }}</span>
                        @if ($item->status !== 'active')
                            <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                        @endif
                    </div>
                </div>

                @if ($canManage)
                    <div class="d-flex gap-2">
                        <a href="{{ route('inventory-items.edit', $item) }}" class="btn btn-outline-primary">
                            <i class="bi bi-pencil me-1"></i>{{ __('Edit') }}
                        </a>
                        <form method="POST" action="{{ route('inventory-items.status', $item) }}"
                              @if ($item->status === 'active') data-confirm="{{ __('Deactivate :name? It will be hidden from staff item lists.', ['name' => $item->name]) }}" @endif>
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $item->status === 'active' ? 'inactive' : 'active' }}">
                            @if ($item->status === 'active')
                                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-slash-circle me-1"></i>{{ __('Deactivate') }}</button>
                            @else
                                <button type="submit" class="btn btn-outline-success"><i class="bi bi-check-circle me-1"></i>{{ __('Reactivate') }}</button>
                            @endif
                        </form>
                    </div>
                @endif
            </div>

            <dl class="row small mt-3 mb-0">
                <dt class="col-sm-3 text-muted fw-normal">{{ __('Unit') }}</dt>
                <dd class="col-sm-9">{{ $item->unit_of_measure }}</dd>
                @if ($canManage)
                    <dt class="col-sm-3 text-muted fw-normal">{{ __('Unit Cost') }}</dt>
                    <dd class="col-sm-9">₱{{ number_format($item->unit_cost, 2) }}</dd>
                @endif
                <dt class="col-sm-3 text-muted fw-normal">{{ __('Reorder Level') }}</dt>
                <dd class="col-sm-9 mb-0"><x-qty :value="$item->reorder_level" :unit="$item->unit_of_measure" /></dd>
            </dl>
        </div>
    </div>

    {{-- Stock by location --}}
    <h3 class="h6 text-uppercase text-muted small">{{ __('Stock on Hand') }}</h3>
    <div class="row g-3 mb-4">
        @foreach ($locations as $location)
            @php
                $qty = (float) ($stocks->get($location->id)?->quantity_on_hand ?? 0);
                $low = $qty <= (float) $item->reorder_level;
            @endphp
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="small text-muted text-truncate">{{ $location->name }}</div>
                        <div class="fs-4 fw-semibold {{ $low ? 'text-danger' : '' }}">
                            <x-qty :value="$qty" :unit="$item->unit_of_measure" />
                        </div>
                        @if ($low)
                            <span class="badge text-bg-danger">{{ __('Low stock') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Movement history (audit trail for this item) --}}
    <h3 class="h6 text-uppercase text-muted small">{{ __('Movement History') }}</h3>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Location') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th class="text-end">{{ __('Quantity') }}</th>
                        <th class="text-end">{{ __('Balance After') }}</th>
                        <th>{{ __('Recorded By') }}</th>
                        <th>{{ __('Remarks') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $tx)
                        <tr>
                            <td class="text-nowrap">{{ $tx->transaction_date->format('M d, Y h:i A') }}</td>
                            <td>{{ $tx->location->name }}</td>
                            <td>{{ str_replace('_', ' ', ucfirst($tx->transaction_type)) }}</td>
                            <td class="text-end"><x-qty :value="$tx->quantity" /></td>
                            <td class="text-end"><x-qty :value="$tx->balance_after" /></td>
                            <td>{{ $tx->user->name }}</td>
                            <td class="small">{{ $tx->remarks }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No stock movements recorded yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($transactions->hasPages())
            <div class="card-footer bg-white">{{ $transactions->links() }}</div>
        @endif
    </div>
</x-app-layout>
