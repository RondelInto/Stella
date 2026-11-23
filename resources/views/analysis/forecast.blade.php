<x-app-layout>
    <x-slot name="title">{{ __('Sales Forecast #:id', ['id' => $run->id]) }}</x-slot>
    <x-slot name="header">{{ __('Sales Forecast — Run #:id', ['id' => $run->id]) }}</x-slot>

    @push('scripts')
        @vite('resources/js/charts.js')
    @endpush

    @php
        $models = $run->model_parameters['models'] ?? [];
        $methodCounts = collect($models)->countBy('method');

        // History dates (last 8 weeks of the learning period) + forecast dates, for the chart.
        $historyDates = [];
        for ($d = \Illuminate\Support\Carbon::parse($history['start']); $d->lte($run->period_end); $d->addDay()) {
            $historyDates[] = $d->toDateString();
        }
        $forecastDates = $dates->map(fn ($d) => $d->toDateString())->all();

        $chartFor = function ($key, $rows) use ($history, $historyDates, $forecastDates) {
            $sold = $history['sold'][$key] ?? [];
            $predicted = $rows->keyBy(fn ($f) => $f->forecast_date->toDateString());
            $labels = array_merge($historyDates, $forecastDates);

            return json_encode([
                'type' => 'line',
                'data' => [
                    'labels' => array_map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('M d'), $labels),
                    'datasets' => [
                        ['label' => __('Actual sold'), 'data' => array_merge(array_map(fn ($d) => $sold[$d] ?? 0, $historyDates), array_fill(0, count($forecastDates), null)),
                         'borderColor' => '#6c757d', 'backgroundColor' => '#6c757d', 'pointRadius' => 2, 'tension' => 0.2],
                        ['label' => __('Forecast'), 'data' => array_merge(array_fill(0, count($historyDates), null), array_map(fn ($d) => (float) ($predicted[$d]->predicted_quantity ?? 0), $forecastDates)),
                         'borderColor' => '#0d6efd', 'backgroundColor' => '#0d6efd', 'borderDash' => [6, 4], 'pointRadius' => 3, 'tension' => 0.2],
                    ],
                ],
                'options' => ['scales' => ['y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => __('Quantity')]]]],
            ]);
        };
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
                    <div class="small text-muted">{{ __('Learned From') }}</div>
                    <div class="fw-semibold">{{ $run->period_start->format('M d, Y') }} – {{ $run->period_end->format('M d, Y') }}</div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="small text-muted">{{ __('Forecast For') }}</div>
                    <div class="fw-semibold">
                        @if ($dates->isNotEmpty())
                            {{ $dates->first()->format('M d') }} – {{ $dates->last()->format('M d, Y') }}
                        @endif
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="small text-muted">{{ __('Average R² (regression models)') }}</div>
                    <div class="fs-5 fw-semibold">{{ $run->accuracy_score !== null ? number_format((float) $run->accuracy_score, 2) : '—' }}</div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="small text-muted">{{ __('Models') }}</div>
                    <div class="fw-semibold">
                        {{ __(':r regression, :a average', ['r' => $methodCounts['regression'] ?? 0, 'a' => $methodCounts['average'] ?? 0]) }}
                    </div>
                    <div class="small text-muted">{{ $run->runBy->name }}, {{ $run->run_at->format('M d, Y g:i A') }}</div>
                </div>
            </div>
        </div>
    </div>

    @if ($forecasts->isNotEmpty())
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h2 class="h6 mb-0">{{ __('Actual vs. Forecast') }}</h2>
                    <select class="form-select form-select-sm w-auto" data-chart-switch="#forecastChart" aria-label="{{ __('Product and location') }}">
                        @foreach ($forecasts as $key => $rows)
                            <option data-chart="{{ $chartFor($key, $rows) }}">{{ $rows->first()->product->pos_product_name }} — {{ $rows->first()->location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="height: 300px">
                    <canvas id="forecastChart" data-chart="{{ $chartFor($forecasts->keys()->first(), $forecasts->first()) }}" role="img" aria-label="{{ __('Line chart of actual and forecast sales') }}"></canvas>
                </div>
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Location') }}</th>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Method') }}</th>
                        <th class="text-end">R²</th>
                        @foreach ($dates as $date)
                            <th class="text-end text-nowrap">{{ $date->format('D') }}<div class="small text-muted fw-normal">{{ $date->format('M d') }}</div></th>
                        @endforeach
                        <th class="text-end">{{ __('Total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($forecasts as $key => $rows)
                        @php
                            $model = $models[$key] ?? [];
                            $byDate = $rows->keyBy(fn ($f) => $f->forecast_date->toDateString());
                        @endphp
                        <tr>
                            <td>{{ $rows->first()->location->name }}</td>
                            <td class="fw-semibold">{{ $rows->first()->product->pos_product_name }}</td>
                            <td class="small">
                                @if (($model['method'] ?? '') === 'regression')
                                    <span title="{{ __('Trend: :t per day', ['t' => $model['trend_per_day']]) }}">{{ __('Regression') }}</span>
                                @else
                                    <span class="text-muted" title="{{ __('Fewer than :min days of sales (or not every weekday twice)', ['min' => $run->model_parameters['minimum_days'] ?? 28]) }}">{{ __('Average') }}</span>
                                @endif
                                <div class="text-muted">{{ __(':n days', ['n' => $model['days'] ?? 0]) }}</div>
                            </td>
                            <td class="text-end">{{ isset($model['r2']) ? number_format($model['r2'], 2) : '—' }}</td>
                            @foreach ($dates as $date)
                                <td class="text-end"><x-qty :value="$byDate[$date->toDateString()]->predicted_quantity ?? 0" /></td>
                            @endforeach
                            <td class="text-end fw-semibold"><x-qty :value="$rows->sum(fn ($f) => (float) $f->predicted_quantity)" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="alert alert-light border small mt-3">
        <strong>{{ __('How this forecast works.') }}</strong>
        {{ __('For each product at each location, a multiple linear regression learns the daily quantity sold from two things: the overall trend (is it selling more or less over time?) and the day of the week (e.g. Saturdays sell more). It needs at least :min days of sales with every weekday at least twice; otherwise the forecast is the plain daily average.', ['min' => $run->model_parameters['minimum_days'] ?? 28]) }}
        {{ __('R² (0 to 1) shows how well the model fits past sales: closer to 1 is better. Forecasts are a guide for planning, not a guarantee.') }}
    </div>
</x-app-layout>
