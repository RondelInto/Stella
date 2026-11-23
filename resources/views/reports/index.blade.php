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

        @can('view-all-reports')
            @foreach ([['Product Profitability', 'cash-coin'], ['Sales Frequency', 'graph-up']] as [$title, $icon])
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm h-100 bg-light">
                        <div class="card-body">
                            <h2 class="h6 text-muted"><i class="bi bi-{{ $icon }} me-2"></i>{{ __($title) }}</h2>
                            <p class="small text-muted mb-0">{{ __('Available after POS sales data is imported (modules 7–8).') }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        @endcan
    </div>
</x-app-layout>
