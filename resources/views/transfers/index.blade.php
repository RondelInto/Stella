<x-app-layout>
    <x-slot name="title">{{ __('Stock Transfers') }}</x-slot>
    <x-slot name="header">{{ __('Stock Transfers') }}</x-slot>

    @if ($awaitingReceipt > 0)
        <div class="alert alert-primary d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span><i class="bi bi-truck me-1"></i>{{ trans_choice(':count transfer is on its way to your branch.|:count transfers are on their way to your branch.', $awaitingReceipt) }}</span>
            <a href="{{ route('stock-transfers.index', ['status' => 'released']) }}" class="btn btn-sm btn-primary">{{ __('Show them') }}</a>
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">{{ __('Stock sent from the commissary to the branches.') }}</p>
        @can('prepare-stock-transfers')
            <a href="{{ route('stock-transfers.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>{{ __('New Transfer') }}
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('stock-transfers.index') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label for="search" class="form-label small mb-1">{{ __('Reference No.') }}</label>
                    <input type="search" id="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="TR-…">
                </div>
                <div class="col-6 col-md-2">
                    <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach (['pending' => 'Pending', 'released' => 'Released (in transit)', 'received' => 'Received', 'cancelled' => 'Cancelled'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
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
                    <label for="from" class="form-label small mb-1">{{ __('From') }}</label>
                    <input type="date" id="from" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-6 col-md-2">
                    <label for="to" class="form-label small mb-1">{{ __('To') }}</label>
                    <input type="date" id="to" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
                    <a href="{{ route('stock-transfers.index') }}" class="btn btn-outline-secondary" title="{{ __('Clear filters') }}"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Reference No.') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('From') }}</th>
                        <th>{{ __('To') }}</th>
                        <th class="text-center">{{ __('Items') }}</th>
                        <th>{{ __('Prepared By') }}</th>
                        <th>{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transfers as $transfer)
                        <tr>
                            <td><a href="{{ route('stock-transfers.show', $transfer) }}" class="fw-semibold text-decoration-none">{{ $transfer->reference_no }}</a></td>
                            <td class="text-nowrap">{{ $transfer->transfer_date->format('M d, Y') }}</td>
                            <td>{{ $transfer->sourceLocation->name }}</td>
                            <td>{{ $transfer->destinationLocation->name }}</td>
                            <td class="text-center">{{ $transfer->details_count }}</td>
                            <td>{{ $transfer->preparedBy->name }}</td>
                            <td><x-transfer-status-badge :status="$transfer->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No transfers found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($transfers->hasPages())
            <div class="card-footer bg-white">{{ $transfers->links() }}</div>
        @endif
    </div>
</x-app-layout>
