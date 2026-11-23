<x-app-layout>
    <x-slot name="title">{{ __('Stock Discrepancy Report') }}</x-slot>
    <x-slot name="header">{{ __('Stock Discrepancy Report') }}</x-slot>

    @php
        $locationFilter = $locations->count() > 1
            ? new \Illuminate\Support\HtmlString(view('reports._location-filter', compact('locations', 'selectedLocation'))->render())
            : null;
    @endphp

    @include('reports._header', [
        'title' => __('Stock Discrepancy Report'),
        'route' => 'reports.discrepancies',
        'extraFilters' => $locationFilter,
    ])

    {{-- Summary per location --}}
    <h2 class="h6 text-uppercase text-muted small">{{ __('Summary by Location') }}</h2>
    <div class="card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Location') }}</th>
                        <th class="text-center">{{ __('Count Discrepancies') }}</th>
                        <th class="text-center">{{ __('Short Transfer Lines') }}</th>
                        @if ($showValues)
                            <th class="text-end">{{ __('Value Lost (₱)') }}</th>
                            <th class="text-end">{{ __('Value Found (₱)') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summary as $s)
                        <tr>
                            <td class="fw-semibold">{{ $s->location->name }}</td>
                            <td class="text-center">{{ $s->count_lines }}</td>
                            <td class="text-center">{{ $s->transfer_lines }}</td>
                            @if ($showValues)
                                <td class="text-end text-danger">₱{{ number_format((float) $s->loss_value, 2) }}</td>
                                <td class="text-end text-success">₱{{ number_format((float) $s->gain_value, 2) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $showValues ? 5 : 3 }}" class="text-center text-muted py-3">{{ __('No discrepancies in this period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Detail --}}
    <h2 class="h6 text-uppercase text-muted small">{{ __('Details') }}</h2>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Source') }}</th>
                        <th>{{ __('Location') }}</th>
                        <th>{{ __('Item') }}</th>
                        <th class="text-end">{{ __('Expected') }}</th>
                        <th class="text-end">{{ __('Actual') }}</th>
                        <th class="text-end">{{ __('Difference') }}</th>
                        @if ($showValues)
                            <th class="text-end">{{ __('Value (₱)') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $line)
                        <tr>
                            <td class="text-nowrap">{{ $line->date->format('M d, Y') }}</td>
                            <td>
                                {{ __($line->source) }}
                                <div class="small"><a href="{{ $line->url }}">{{ $line->reference }}</a></div>
                            </td>
                            <td>{{ $line->location->name }}</td>
                            <td>
                                {{ $line->item->name }}
                                <div class="small text-muted"><code>{{ $line->item->item_code }}</code>
                                    @if (! empty($line->remarks)) · {{ $line->remarks }} @endif
                                </div>
                            </td>
                            <td class="text-end"><x-qty :value="$line->expected" /></td>
                            <td class="text-end"><x-qty :value="$line->actual" /></td>
                            <td class="text-end text-nowrap"><x-variance :value="$line->difference" :unit="$line->item->unit_of_measure" /></td>
                            @if ($showValues)
                                <td class="text-end {{ (float) $line->value < 0 ? 'text-danger' : 'text-success' }}">{{ number_format((float) $line->value, 2) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $showValues ? 8 : 7 }}" class="text-center text-muted py-4">{{ __('No discrepancies in this period.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-muted mt-2">
        {{ __('Stock count: counted vs. system quantity. Transfer: received vs. sent, shown under the receiving branch.') }}
        @if ($showValues)
            {{ __('Peso values use each item\'s current unit cost.') }}
        @endif
    </p>
</x-app-layout>
