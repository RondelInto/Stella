<x-app-layout>
    <x-slot name="title">{{ __('Sales Analysis') }}</x-slot>
    <x-slot name="header">{{ __('Sales Analysis') }}</x-slot>

    @php
        $defaultTo = ($lastSale && $lastSale->lt(today()) ? $lastSale : today())->toDateString();
        $defaultFrom = $firstSale
            ? $firstSale->copy()->max(\Illuminate\Support\Carbon::parse($defaultTo)->subDays(89))->toDateString()
            : today()->subDays(29)->toDateString();
    @endphp

    <p class="text-muted">
        {{ __('Analyzes the sales imported from the POS. Each run is saved, so its results stay the same even if costs change later.') }}
        @if ($firstSale)
            {{ __('Imported sales cover :from to :to.', ['from' => $firstSale->format('M d, Y'), 'to' => $lastSale->format('M d, Y')]) }}
        @else
            <strong>{{ __('No POS sales have been imported yet.') }}</strong>
        @endif
    </p>

    @can('run-sales-analysis')
        <div class="row g-4 mb-4">
            {{-- Product analysis: profit, frequency, clustering --}}
            <div class="col-12 col-xl-6">
                <form method="POST" action="{{ route('analysis.products.store') }}" class="card border-0 shadow-sm h-100">
                    @csrf
                    <div class="card-body">
                        <h2 class="h6"><i class="bi bi-diagram-3 me-1 text-brand"></i>{{ __('Product Performance (Profit, Frequency, Clustering)') }}</h2>
                        <p class="small text-muted">
                            {{ __('Profit per product, how often each is bought, and k-means groups (High / Medium / Low performers).') }}
                        </p>
                        <div class="row g-2">
                            <div class="col-6">
                                <label for="p_from" class="form-label small mb-1">{{ __('From') }}</label>
                                <input type="date" id="p_from" name="from" class="form-control" value="{{ old('from', $defaultFrom) }}" max="{{ today()->toDateString() }}" required>
                            </div>
                            <div class="col-6">
                                <label for="p_to" class="form-label small mb-1">{{ __('To') }}</label>
                                <input type="date" id="p_to" name="to" class="form-control" value="{{ old('to', $defaultTo) }}" max="{{ today()->toDateString() }}" required>
                            </div>
                            <div class="col-12">
                                <label for="p_location" class="form-label small mb-1">{{ __('Location') }}</label>
                                <select id="p_location" name="location_id" class="form-select">
                                    <option value="">{{ __('All locations') }}</option>
                                    @foreach ($locations as $loc)
                                        <option value="{{ $loc->id }}" @selected((int) old('location_id') === $loc->id)>{{ $loc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white text-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-play-fill me-1"></i>{{ __('Run Product Analysis') }}</button>
                    </div>
                </form>
            </div>

            {{-- Forecast: regression --}}
            <div class="col-12 col-xl-6">
                <form method="POST" action="{{ route('analysis.forecast.store') }}" class="card border-0 shadow-sm h-100">
                    @csrf
                    <div class="card-body">
                        <h2 class="h6"><i class="bi bi-graph-up-arrow me-1 text-brand"></i>{{ __('Sales Forecast (Regression)') }}</h2>
                        <p class="small text-muted">
                            {{ __('Learns from the sales in this period and predicts the next :days days for each product at each location. Best with at least :min days of sales; use sales up to yesterday for planning.', ['days' => \App\Services\Analysis\SalesForecastService::HORIZON, 'min' => \App\Services\Analysis\SalesForecastService::MIN_DAYS]) }}
                        </p>
                        <div class="row g-2">
                            <div class="col-6">
                                <label for="f_from" class="form-label small mb-1">{{ __('Learn from') }}</label>
                                <input type="date" id="f_from" name="from" class="form-control" value="{{ old('from', $defaultFrom) }}" max="{{ today()->toDateString() }}" required>
                            </div>
                            <div class="col-6">
                                <label for="f_to" class="form-label small mb-1">{{ __('To') }}</label>
                                <input type="date" id="f_to" name="to" class="form-control" value="{{ old('to', $defaultTo) }}" max="{{ today()->toDateString() }}" required>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white text-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-play-fill me-1"></i>{{ __('Run Forecast') }}</button>
                    </div>
                </form>
            </div>
        </div>
        @if ($errors->any())
            <div class="alert alert-danger">@foreach ($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>
        @endif
    @endcan

    <h2 class="h6 text-uppercase text-muted small">{{ __('Saved Runs') }}</h2>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Run') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Sales Period') }}</th>
                        <th>{{ __('Run By') }}</th>
                        <th class="text-end">{{ __('Average R²') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr>
                            <td>
                                <a href="{{ route('analysis.show', $run) }}" class="fw-semibold text-decoration-none">#{{ $run->id }}</a>
                                <div class="small text-muted">{{ $run->run_at->format('M d, Y g:i A') }}</div>
                            </td>
                            <td>
                                @if ($run->analysis_type === \App\Models\AnalysisRun::TYPE_CLUSTERING)
                                    <i class="bi bi-diagram-3 me-1"></i>{{ __('Product Performance') }}
                                @else
                                    <i class="bi bi-graph-up-arrow me-1"></i>{{ __('Sales Forecast') }}
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $run->period_start->format('M d, Y') }} – {{ $run->period_end->format('M d, Y') }}</td>
                            <td>{{ $run->runBy->name }}</td>
                            <td class="text-end">{{ $run->accuracy_score !== null ? number_format((float) $run->accuracy_score, 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No analysis has been run yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($runs->hasPages())
            <div class="card-footer bg-white">{{ $runs->links() }}</div>
        @endif
    </div>
</x-app-layout>
