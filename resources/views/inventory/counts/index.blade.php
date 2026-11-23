<x-app-layout>
    <x-slot name="title">{{ __('Stock Counts') }}</x-slot>
    <x-slot name="header">{{ __('Stock Counts') }}</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">{{ __('Closing counts compare physical stock with the system and correct differences.') }}</p>
        <div class="d-flex gap-2">
            <a href="{{ route('stock-counts.discrepancies') }}" class="btn btn-outline-danger">
                <i class="bi bi-exclamation-diamond me-1"></i>{{ __('Discrepancies') }}
            </a>
            @can('record-stock-counts')
                <a href="{{ route('stock-counts.create') }}" class="btn btn-primary">
                    <i class="bi bi-clipboard-plus me-1"></i>{{ __('Start Count') }}
                </a>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('stock-counts.index') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                @if ($locations->count() > 1)
                    <div class="col-6 col-md-3">
                        <label for="location" class="form-label small mb-1">{{ __('Location') }}</label>
                        <select id="location" name="location" class="form-select">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected(($filters['location'] ?? '') == $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-6 col-md-3">
                    <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>{{ __('In progress') }}</option>
                        <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>{{ __('Completed') }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
                    <a href="{{ route('stock-counts.index') }}" class="btn btn-outline-secondary" title="{{ __('Clear filters') }}"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>{{ __('Count Date') }}</th>
                        <th>{{ __('Location') }}</th>
                        <th>{{ __('Counted By') }}</th>
                        <th class="text-center">{{ __('Items') }}</th>
                        <th class="text-center">{{ __('Discrepancies') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($counts as $count)
                        <tr>
                            <td>{{ $count->id }}</td>
                            <td>{{ $count->count_date->format('M d, Y') }}</td>
                            <td>{{ $count->location->name }}</td>
                            <td>{{ $count->countedBy->name }}</td>
                            <td class="text-center">{{ $count->details_count }}</td>
                            <td class="text-center">
                                @if ($count->isDraft())
                                    <span class="text-muted">—</span>
                                @elseif ($count->discrepancies_count > 0)
                                    <span class="badge text-bg-danger">{{ $count->discrepancies_count }}</span>
                                @else
                                    <span class="badge text-bg-success">0</span>
                                @endif
                            </td>
                            <td>
                                @if ($count->isDraft())
                                    <span class="badge text-bg-warning">{{ __('In progress') }}</span>
                                @else
                                    <span class="badge text-bg-success">{{ __('Completed') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('stock-counts.show', $count) }}" class="btn btn-sm btn-outline-primary">
                                    {{ $count->isDraft() ? __('Continue') : __('View') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No stock counts yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($counts->hasPages())
            <div class="card-footer bg-white">{{ $counts->links() }}</div>
        @endif
    </div>
</x-app-layout>
