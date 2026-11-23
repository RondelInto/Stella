{{-- Product analysis results (used by the run page and report R6). Variable: $results --}}
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Product') }}</th>
                    <th>{{ __('Group') }}</th>
                    <th class="text-end">{{ __('Qty Sold') }}</th>
                    <th class="text-end">{{ __('Receipts') }}</th>
                    <th class="text-end">{{ __('Net Sales (₱)') }}</th>
                    <th class="text-end">{{ __('Unit Cost (₱)') }}</th>
                    <th class="text-end">{{ __('Total Cost (₱)') }}</th>
                    <th class="text-end">{{ __('Gross Profit (₱)') }}</th>
                    <th class="text-end">{{ __('Margin') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($results as $r)
                    <tr>
                        <td class="fw-semibold">{{ $r->product->pos_product_name }}</td>
                        <td><x-cluster-badge :label="$r->cluster_label" /></td>
                        <td class="text-end"><x-qty :value="$r->total_quantity_sold" /></td>
                        <td class="text-end">{{ number_format($r->sales_frequency) }}</td>
                        <td class="text-end">{{ number_format((float) $r->total_sales, 2) }}</td>
                        @if ($r->unit_cost_used === null)
                            <td colspan="4" class="text-center small text-warning-emphasis">{{ __('Cost not set') }}</td>
                        @else
                            <td class="text-end">{{ number_format((float) $r->unit_cost_used, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $r->total_cost, 2) }}</td>
                            <td class="text-end {{ (float) $r->gross_profit < 0 ? 'text-danger' : '' }}">{{ number_format((float) $r->gross_profit, 2) }}</td>
                            <td class="text-end">{{ $r->profit_margin !== null ? number_format((float) $r->profit_margin * 100, 1).'%' : '—' }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">{{ __('No results.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
