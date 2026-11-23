<x-app-layout>
    <x-slot name="title">{{ __('Product Performance #:id', ['id' => $run->id]) }}</x-slot>
    <x-slot name="header">{{ __('Product Performance — Run #:id', ['id' => $run->id]) }}</x-slot>

    @push('scripts')
        @vite('resources/js/charts.js')
    @endpush

    @php
        $params = $run->model_parameters ?? [];
        $withCost = $results->whereNotNull('gross_profit');
        $colors = ['High performer' => '#198754', 'Medium performer' => '#0d6efd', 'Low performer' => '#dc3545'];

        $groups = $results->whereNotNull('cluster_label')->groupBy('cluster_label');
        $chart = [
            'type' => 'scatter',
            'data' => ['datasets' => collect(\App\Services\Analysis\ProductAnalysisService::CLUSTER_LABELS)
                ->filter(fn ($label) => $groups->has($label))
                ->map(fn ($label) => [
                    'label' => __($label),
                    'xLabel' => __('receipts'),
                    'yLabel' => __('margin %'),
                    'backgroundColor' => $colors[$label],
                    'pointRadius' => 6,
                    'data' => $groups[$label]->map(fn ($r) => [
                        'x' => $r->sales_frequency,
                        'y' => round((float) $r->profit_margin * 100, 1),
                        'name' => $r->product->pos_product_name,
                    ])->values(),
                ])->values()],
            'options' => ['scales' => [
                'x' => ['title' => ['display' => true, 'text' => __('Sales frequency (receipts)')], 'beginAtZero' => true],
                'y' => ['title' => ['display' => true, 'text' => __('Profit margin (%)')]],
            ]],
        ];
    @endphp

    <nav aria-label="breadcrumb" class="no-print">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('analysis.index') }}">{{ __('Sales Analysis') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">#{{ $run->id }}</li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <div class="small text-muted">{{ __('Sales Period') }}</div>
                    <div class="fw-semibold">{{ $run->period_start->format('M d, Y') }} – {{ $run->period_end->format('M d, Y') }}</div>
                    <div class="small text-muted">{{ $location?->name ?? __('All locations') }}</div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="small text-muted">{{ __('Net Sales') }}</div>
                    <div class="fs-5 fw-semibold">₱{{ number_format($results->sum(fn ($r) => (float) $r->total_sales), 2) }}</div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="small text-muted">{{ __('Gross Profit (products with cost)') }}</div>
                    <div class="fs-5 fw-semibold">₱{{ number_format($withCost->sum(fn ($r) => (float) $r->gross_profit), 2) }}</div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="small text-muted">{{ __('Run') }}</div>
                    <div class="fw-semibold">{{ $run->run_at->format('M d, Y g:i A') }}</div>
                    <div class="small text-muted">{{ $run->runBy->name }}</div>
                </div>
            </div>
        </div>
    </div>

    @if (($params['products_without_cost'] ?? 0) > 0)
        <div class="alert alert-warning small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ __(':count product(s) have no unit cost, so their profit is unknown and they are not grouped. Set the cost on the linked inventory item, then run again.', ['count' => $params['products_without_cost']]) }}
        </div>
    @endif
    @if (($params['unmatched_rows_skipped'] ?? 0) > 0)
        <div class="alert alert-light border small">
            <i class="bi bi-info-circle me-1"></i>
            {{ __(':count sales row(s) were skipped because their POS product is not linked to a product yet.', ['count' => number_format($params['unmatched_rows_skipped'])]) }}
        </div>
    @endif

    {{-- Clustering --}}
    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6">{{ __('K-means Groups: Frequency vs. Profit Margin') }}</h2>
                    @if ($groups->isEmpty())
                        <p class="text-muted small mb-0">
                            {{ __('Grouping needs at least :k products with a known unit cost.', ['k' => \App\Services\Analysis\ProductAnalysisService::CLUSTERS]) }}
                        </p>
                    @else
                        <div style="height: 340px"><canvas data-chart="{{ json_encode($chart) }}" aria-label="{{ __('Scatter chart of product groups') }}" role="img"></canvas></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6">{{ __('Group Summary') }}</h2>
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('Group') }}</th>
                                <th class="text-end">{{ __('Products') }}</th>
                                <th class="text-end">{{ __('Avg. Receipts') }}</th>
                                <th class="text-end">{{ __('Avg. Margin') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (\App\Services\Analysis\ProductAnalysisService::CLUSTER_LABELS as $label)
                                @continue(! $groups->has($label))
                                <tr>
                                    <td><x-cluster-badge :label="$label" /></td>
                                    <td class="text-end">{{ $groups[$label]->count() }}</td>
                                    <td class="text-end">{{ number_format($groups[$label]->avg('sales_frequency'), 1) }}</td>
                                    <td class="text-end">{{ number_format($groups[$label]->avg(fn ($r) => (float) $r->profit_margin) * 100, 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="small text-muted mb-0">
                        {{ __('Method: k-means, :k groups, on sales frequency and profit margin (both scaled 0–1). Groups are named by their average: High performers sell often at a good margin; Low performers sell rarely or at a thin margin.', ['k' => $params['clusters'] ?? 3]) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Detail --}}
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 text-uppercase text-muted small mb-0">{{ __('Products') }}</h2>
        @can('view-all-reports')
            <div class="d-flex gap-2">
                <a href="{{ route('reports.profitability', ['run' => $run->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-cash-coin me-1"></i>{{ __('Profitability Report') }}</a>
                <a href="{{ route('reports.sales-frequency', ['run' => $run->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-graph-up me-1"></i>{{ __('Frequency Report') }}</a>
            </div>
        @endcan
    </div>
    @include('analysis._results-table', ['results' => $results])

    <p class="small text-muted mt-2">
        {{ __('Imports used:') }}
        @foreach ($run->batches as $batch)
            <a href="{{ route('sales-imports.show', $batch) }}">#{{ $batch->id }}</a> ({{ $batch->location->name }})@if (! $loop->last), @endif
        @endforeach
    </p>
</x-app-layout>
