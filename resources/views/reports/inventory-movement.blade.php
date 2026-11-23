<x-app-layout>
    <x-slot name="title">{{ __('Inventory Movement Report') }}</x-slot>
    <x-slot name="header">{{ __('Inventory Movement Report') }}</x-slot>

    @include('reports._header', [
        'title' => __('Inventory Movement Report'),
        'route' => 'reports.inventory-movement',
        'subtitle' => $location->name,
        'extraFilters' => new \Illuminate\Support\HtmlString(view('reports._movement-filters', compact('locations', 'location', 'categories', 'filters'))->render()),
    ])

    <p class="small text-muted no-print">
        <strong>{{ $location->name }}</strong>: {{ __('Opening + In − Out = Closing. Items with no movements up to the end date are not listed.') }}
    </p>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th rowspan="2">{{ __('Item') }}</th>
                        <th rowspan="2" class="text-end">{{ __('Opening') }}</th>
                        <th colspan="5" class="text-center border-start">{{ __('Stock In') }}</th>
                        <th colspan="6" class="text-center border-start">{{ __('Stock Out') }}</th>
                        <th rowspan="2" class="text-end border-start">{{ __('Closing') }}</th>
                    </tr>
                    <tr class="small">
                        <th class="text-end border-start">{{ __('Received') }}</th>
                        <th class="text-end">{{ __('Transfer In') }}</th>
                        <th class="text-end">{{ __('Produced') }}</th>
                        <th class="text-end">{{ __('Count +') }}</th>
                        <th class="text-end fw-bold">{{ __('Total In') }}</th>
                        <th class="text-end border-start">{{ __('Issued') }}</th>
                        <th class="text-end">{{ __('Damaged') }}</th>
                        <th class="text-end">{{ __('Transfer Out') }}</th>
                        <th class="text-end">{{ __('Used') }}</th>
                        <th class="text-end">{{ __('Count −') }}</th>
                        <th class="text-end fw-bold">{{ __('Total Out') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $row->name }}</div>
                                <div class="small text-muted"><code>{{ $row->item_code }}</code> · {{ $row->category }} · {{ $row->unit_of_measure }}</div>
                            </td>
                            <td class="text-end"><x-qty :value="$row->opening" /></td>
                            <td class="text-end border-start"><x-qty :value="$row->received" /></td>
                            <td class="text-end"><x-qty :value="$row->transfer_in" /></td>
                            <td class="text-end"><x-qty :value="$row->produced" /></td>
                            <td class="text-end"><x-qty :value="$row->count_gain" /></td>
                            <td class="text-end fw-semibold text-success"><x-qty :value="$row->total_in" /></td>
                            <td class="text-end border-start"><x-qty :value="$row->issued" /></td>
                            <td class="text-end"><x-qty :value="$row->damaged" /></td>
                            <td class="text-end"><x-qty :value="$row->transfer_out" /></td>
                            <td class="text-end"><x-qty :value="$row->used" /></td>
                            <td class="text-end"><x-qty :value="$row->count_loss" /></td>
                            <td class="text-end fw-semibold text-danger"><x-qty :value="$row->total_out" /></td>
                            <td class="text-end fw-bold border-start"><x-qty :value="$row->closing" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="14" class="text-center text-muted py-4">{{ __('No stock movements at this location up to the selected date.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-muted mt-2">{{ __(':count item(s). Quantities are in each item\'s own unit.', ['count' => $rows->count()]) }}</p>
</x-app-layout>
