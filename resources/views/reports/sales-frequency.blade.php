<x-app-layout>
    <x-slot name="title">{{ __('Sales Frequency Report') }}</x-slot>
    <x-slot name="header">{{ __('Sales Frequency Report') }}</x-slot>

    @include('reports._run-header', ['title' => __('Sales Frequency Report'), 'route' => 'reports.sales-frequency'])

    @if ($run)
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-end">#</th>
                            <th>{{ __('Product') }}</th>
                            <th class="text-end">{{ __('Receipts') }}</th>
                            <th class="text-end">{{ __('Receipts per Day') }}</th>
                            <th class="text-end">{{ __('Qty Sold') }}</th>
                            <th class="text-end">{{ __('Qty per Receipt') }}</th>
                            <th class="text-end">{{ __('Net Sales (₱)') }}</th>
                            <th>{{ __('Group') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($results as $r)
                            <tr>
                                <td class="text-end text-muted">{{ $loop->iteration }}</td>
                                <td class="fw-semibold">{{ $r->product->pos_product_name }}</td>
                                <td class="text-end">{{ number_format($r->sales_frequency) }}</td>
                                <td class="text-end">{{ number_format($r->receipts_per_day, 2) }}</td>
                                <td class="text-end"><x-qty :value="$r->total_quantity_sold" /></td>
                                <td class="text-end">{{ number_format($r->qty_per_receipt, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $r->total_sales, 2) }}</td>
                                <td><x-cluster-badge :label="$r->cluster_label" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No results.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <p class="small text-muted mt-2">
            {{ __('Receipts = how many receipts included the product (how often customers bought it). Per day = receipts ÷ :days days in the period.', ['days' => $days]) }}
        </p>
    @endif
</x-app-layout>
