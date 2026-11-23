<x-app-layout>
    <x-slot name="title">{{ __('Reports') }}</x-slot>
    <x-slot name="header">{{ __('Reports') }}</x-slot>

    <p class="text-muted">
        @can('view-all-reports')
            {{ __('Centralized reports for all branches and the commissary.') }}
        @else
            {{ __('Reports for your branch.') }}
        @endcan
        {{ __('Every report can be printed (or saved as PDF) and downloaded as CSV for Excel.') }}
    </p>

    @php
        $reports = [
            ['route' => 'reports.inventory-movement', 'icon' => 'arrow-left-right', 'title' => __('Inventory Movement'),
             'text' => __('Opening balance, stock in, stock out, and closing balance per item for a period.')],
            ['route' => 'reports.discrepancies', 'icon' => 'exclamation-diamond', 'title' => __('Stock Discrepancies'),
             'text' => __('Differences found in stock counts and short transfer deliveries.')],
            ['route' => 'reports.transfers', 'icon' => 'truck', 'title' => __('Transfers by Branch'),
             'text' => __('Transfers sent to each branch, shortages, and delivery time.')],
        ];
        if (Auth::user()->can('view-production')) {
            $reports[] = ['route' => 'reports.production', 'icon' => 'fire', 'title' => __('Production'),
                'text' => __('Quantities produced per product, ingredients used, and daily output.')];
        }
        if (Auth::user()->can('view-all-reports') && Auth::user()->can('view-sales-analysis')) {
            $reports[] = ['route' => 'reports.profitability', 'icon' => 'cash-coin', 'title' => __('Product Profitability'),
                'text' => __('Net sales, cost, gross profit and margin per product, from a saved sales analysis.')];
            $reports[] = ['route' => 'reports.sales-frequency', 'icon' => 'graph-up', 'title' => __('Sales Frequency'),
                'text' => __('How often each product is bought (receipts), from a saved sales analysis.')];
        }
    @endphp

    <div class="row g-3">
        @foreach ($reports as $report)
            <div class="col-12 col-md-6 col-xl-4">
                <a href="{{ route($report['route']) }}" class="card border-0 shadow-sm h-100 text-decoration-none">
                    <div class="card-body">
                        <h2 class="h6 text-body"><i class="bi bi-{{ $report['icon'] }} me-2 text-brand"></i>{{ $report['title'] }}</h2>
                        <p class="small text-muted mb-0">{{ $report['text'] }}</p>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</x-app-layout>
