<x-app-layout>
    <x-slot name="title">{{ __('POS Sales Imports') }}</x-slot>
    <x-slot name="header">{{ __('POS Sales Imports') }}</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">{{ __('Sales exported from each location\'s POS. This system only reads the exported files; it never connects to the POS.') }}</p>
        <div class="d-flex gap-2">
            <a href="{{ route('sales-imports.unmatched') }}" class="btn btn-outline-warning">
                <i class="bi bi-link-45deg me-1"></i>{{ __('Unmatched Products') }}
                @if ($unmatchedTotal)
                    <span class="badge text-bg-warning ms-1">{{ number_format($unmatchedTotal) }}</span>
                @endif
            </a>
            <a href="{{ route('sales-imports.create') }}" class="btn btn-primary"><i class="bi bi-upload me-1"></i>{{ __('Import Sales') }}</a>
        </div>
    </div>

    <form method="GET" action="{{ route('sales-imports.index') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="location" class="form-label small mb-1">{{ __('Location') }}</label>
                    <select id="location" name="location" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}" @selected((int) ($filters['location'] ?? 0) === $loc->id)>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
                    <a href="{{ route('sales-imports.index') }}" class="btn btn-outline-secondary" title="{{ __('Clear filters') }}"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Import') }}</th>
                        <th>{{ __('Location') }}</th>
                        <th>{{ __('Sales Period') }}</th>
                        <th class="text-end">{{ __('Rows') }}</th>
                        <th class="text-end">{{ __('Net Sales') }}</th>
                        <th class="text-center">{{ __('Unmatched') }}</th>
                        <th>{{ __('Imported') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batches as $batch)
                        <tr>
                            <td>
                                <a href="{{ route('sales-imports.show', $batch) }}" class="fw-semibold text-decoration-none">#{{ $batch->id }}</a>
                                <div class="small text-muted text-truncate" style="max-width: 220px">{{ $batch->file_name }}</div>
                            </td>
                            <td>{{ $batch->location->name }}</td>
                            <td class="text-nowrap">
                                {{ $batch->period_start->format('M d, Y') }}
                                @if (! $batch->period_start->equalTo($batch->period_end))
                                    – {{ $batch->period_end->format('M d, Y') }}
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($batch->total_records) }}</td>
                            <td class="text-end">₱{{ number_format((float) $batch->sales_sum_net_amount, 2) }}</td>
                            <td class="text-center">
                                @if ($batch->unmatched_count)
                                    <span class="badge text-bg-warning">{{ number_format($batch->unmatched_count) }}</span>
                                @else
                                    <i class="bi bi-check-circle text-success" title="{{ __('All rows matched') }}"></i>
                                @endif
                            </td>
                            <td class="small text-nowrap">
                                {{ $batch->imported_at->format('M d, Y g:i A') }}
                                <div class="text-muted">{{ $batch->importedBy->name }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No sales have been imported yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($batches->hasPages())
            <div class="card-footer bg-white">{{ $batches->links() }}</div>
        @endif
    </div>
</x-app-layout>
