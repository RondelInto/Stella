<x-app-layout>
    <x-slot name="title">{{ __('Transfers by Branch') }}</x-slot>
    <x-slot name="header">{{ __('Transfers by Branch') }}</x-slot>

    @include('reports._header', ['title' => __('Transfers by Branch'), 'route' => 'reports.transfers'])

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Branch') }}</th>
                        <th class="text-center">{{ __('Transfers') }}</th>
                        <th class="text-center">{{ __('Pending') }}</th>
                        <th class="text-center">{{ __('In Transit') }}</th>
                        <th class="text-center">{{ __('Received') }}</th>
                        <th class="text-center">{{ __('Cancelled') }}</th>
                        <th class="text-center">{{ __('Item Lines') }}</th>
                        <th class="text-center">{{ __('Short Lines') }}</th>
                        @if ($showValues)
                            <th class="text-end">{{ __('Shortage Value (₱)') }}</th>
                        @endif
                        <th class="text-end">{{ __('Avg. Release → Receipt') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td class="fw-semibold">
                                <a href="{{ route('stock-transfers.index', ['location' => $r->location->id, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
                                   class="text-decoration-none">{{ $r->location->name }}</a>
                            </td>
                            <td class="text-center">{{ $r->total }}</td>
                            <td class="text-center">{{ $r->pending }}</td>
                            <td class="text-center {{ $r->in_transit ? 'fw-semibold text-primary' : '' }}">{{ $r->in_transit }}</td>
                            <td class="text-center">{{ $r->received }}</td>
                            <td class="text-center">{{ $r->cancelled }}</td>
                            <td class="text-center">{{ $r->lines }}</td>
                            <td class="text-center {{ $r->short_lines ? 'fw-semibold text-danger' : '' }}">{{ $r->short_lines }}</td>
                            @if ($showValues)
                                <td class="text-end">{{ number_format((float) $r->shortage_value, 2) }}</td>
                            @endif
                            <td class="text-end">{{ $r->avg_hours !== null ? __(':h hrs', ['h' => $r->avg_hours]) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $showValues ? 10 : 9 }}" class="text-center text-muted py-4">{{ __('No transfers in this period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-muted mt-2">{{ __('Grouped by receiving branch and transfer date. Short lines = items received below the quantity sent.') }}</p>
</x-app-layout>
