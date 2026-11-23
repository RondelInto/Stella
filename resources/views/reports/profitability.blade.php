<x-app-layout>
    <x-slot name="title">{{ __('Product Profitability Report') }}</x-slot>
    <x-slot name="header">{{ __('Product Profitability Report') }}</x-slot>

    @include('reports._run-header', ['title' => __('Product Profitability Report'), 'route' => 'reports.profitability'])

    @if ($run)
        @php
            $withCost = $results->whereNotNull('gross_profit');
            $sales = $withCost->sum(fn ($r) => (float) $r->total_sales);
            $profit = $withCost->sum(fn ($r) => (float) $r->gross_profit);
        @endphp
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
                <div class="small text-muted">{{ __('Net Sales (all products)') }}</div>
                <div class="fs-5 fw-semibold">₱{{ number_format($results->sum(fn ($r) => (float) $r->total_sales), 2) }}</div>
            </div></div></div>
            <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
                <div class="small text-muted">{{ __('Gross Profit (with cost)') }}</div>
                <div class="fs-5 fw-semibold">₱{{ number_format($profit, 2) }}</div>
            </div></div></div>
            <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
                <div class="small text-muted">{{ __('Overall Margin (with cost)') }}</div>
                <div class="fs-5 fw-semibold">{{ $sales > 0 ? number_format($profit / $sales * 100, 1).'%' : '—' }}</div>
            </div></div></div>
            <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
                <div class="small text-muted">{{ __('Products without Cost') }}</div>
                <div class="fs-5 fw-semibold {{ $results->count() - $withCost->count() ? 'text-warning' : '' }}">{{ $results->count() - $withCost->count() }}</div>
            </div></div></div>
        </div>

        @include('analysis._results-table', ['results' => $results])

        <p class="small text-muted mt-2">
            {{ __('Gross profit = net sales − quantity sold × unit cost of the linked inventory item, as it was when the analysis ran. Sorted by gross profit.') }}
        </p>
    @endif
</x-app-layout>
