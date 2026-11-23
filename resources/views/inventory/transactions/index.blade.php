<x-app-layout>
    <x-slot name="title">{{ __('Transaction History') }}</x-slot>
    <x-slot name="header">{{ __('Transaction History') }}</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">
            <i class="bi bi-shield-check me-1"></i>{{ __('Audit trail of every stock movement. Records cannot be edited or deleted.') }}
        </p>
        @can('record-inventory-transactions')
            <a href="{{ route('inventory-transactions.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>{{ __('Record Transaction') }}
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('inventory-transactions.index') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                @if ($locations->count() > 1)
                    <div class="col-6 col-md-2">
                        <label for="location" class="form-label small mb-1">{{ __('Location') }}</label>
                        <select id="location" name="location" class="form-select">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected(($filters['location'] ?? '') == $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-6 col-md-2">
                    <label for="search" class="form-label small mb-1">{{ __('Item') }}</label>
                    <input type="search" id="search" name="search" class="form-control"
                           value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Name or code') }}">
                </div>
                <div class="col-6 col-md-2">
                    <label for="type" class="form-label small mb-1">{{ __('Type') }}</label>
                    <select id="type" name="type" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="user" class="form-label small mb-1">{{ __('Recorded By') }}</label>
                    <select id="user" name="user" class="form-select">
                        <option value="">{{ __('Anyone') }}</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected(($filters['user'] ?? '') == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-1">
                    <label for="from" class="form-label small mb-1">{{ __('From') }}</label>
                    <input type="date" id="from" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-6 col-md-1">
                    <label for="to" class="form-label small mb-1">{{ __('To') }}</label>
                    <input type="date" id="to" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
                    <a href="{{ route('inventory-transactions.index') }}" class="btn btn-outline-secondary" title="{{ __('Clear filters') }}"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Item') }}</th>
                        <th>{{ __('Location') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th class="text-end">{{ __('Change') }}</th>
                        <th class="text-end">{{ __('Balance After') }}</th>
                        <th>{{ __('Recorded By') }}</th>
                        <th>{{ __('Remarks') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $tx)
                        @php
                            $isDecrease = in_array($tx->transaction_type, \App\Models\InventoryTransaction::DECREASE_TYPES, true)
                                || ($tx->transaction_type === 'count_adjustment' && (float) $tx->quantity < 0);
                        @endphp
                        <tr>
                            <td class="text-nowrap small">
                                {{ $tx->transaction_date->format('M d, Y h:i A') }}
                                @if ($tx->created_at->diffInMinutes($tx->transaction_date, true) > 5)
                                    <div class="text-muted" title="{{ __('When it was entered into the system') }}">
                                        {{ __('entered') }} {{ $tx->created_at->format('M d, h:i A') }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('inventory-items.show', $tx->inventoryItem) }}" class="text-decoration-none">{{ $tx->inventoryItem->name }}</a>
                                <div class="small text-muted"><code>{{ $tx->inventoryItem->item_code }}</code></div>
                            </td>
                            <td>{{ $tx->location->name }}</td>
                            <td>
                                <x-transaction-type-badge :type="$tx->transaction_type" />
                                @if ($tx->stockTransfer)
                                    <div class="small text-muted">{{ $tx->stockTransfer->reference_no }}</div>
                                @endif
                            </td>
                            <td class="text-end text-nowrap fw-semibold {{ $isDecrease ? 'text-danger' : 'text-success' }}">
                                {{ $isDecrease ? '−' : '+' }}<x-qty :value="abs((float) $tx->quantity)" />
                                <span class="text-muted fw-normal small">{{ $tx->inventoryItem->unit_of_measure }}</span>
                            </td>
                            <td class="text-end"><x-qty :value="$tx->balance_after" /></td>
                            <td>{{ $tx->user->name }}</td>
                            <td class="small">{{ $tx->remarks }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No transactions found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($transactions->hasPages())
            <div class="card-footer bg-white">{{ $transactions->links() }}</div>
        @endif
    </div>
</x-app-layout>
