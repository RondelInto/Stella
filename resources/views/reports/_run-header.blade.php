{{--
    Header for reports built from a saved product analysis run (R6, R7): run picker, Print and CSV.
    Variables: $title, $route, $run (nullable), $runs.
--}}
@if ($run)
    <div class="print-only mb-3">
        <div class="fw-bold">{{ config('app.name') }}</div>
        <h1 class="h4 mb-0">{{ $title }}</h1>
        <div class="small">
            {{ $run->period_start->format('M d, Y') }} – {{ $run->period_end->format('M d, Y') }}
            · {{ __('Analysis run #:id', ['id' => $run->id]) }}
            · {{ __('Printed :date by :name', ['date' => now()->format('M d, Y h:i A'), 'name' => Auth::user()->name]) }}
        </div>
    </div>
@endif

<nav aria-label="breadcrumb" class="no-print">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">{{ __('Reports') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
    </ol>
</nav>

@if (! $run)
    <div class="alert alert-info">
        {{ __('This report comes from a saved product analysis. Run one first under Sales Analysis.') }}
        @can('view-sales-analysis')
            <a href="{{ route('analysis.index') }}" class="alert-link">{{ __('Go to Sales Analysis') }}</a>
        @endcan
    </div>
@else
    <form method="GET" action="{{ route($route) }}" class="card border-0 shadow-sm mb-3 no-print">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-6">
                    <label for="run" class="form-label small mb-1">{{ __('Analysis run') }}</label>
                    <select id="run" name="run" class="form-select">
                        @foreach ($runs as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $run->id)>
                                #{{ $option->id }}: {{ $option->period_start->format('M d, Y') }} – {{ $option->period_end->format('M d, Y') }}
                                ({{ __('run :date', ['date' => $option->run_at->format('M d')]) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md d-flex flex-wrap gap-2 justify-content-md-end">
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i>{{ __('Show') }}</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>{{ __('Print / PDF') }}
                    </button>
                    <button type="submit" name="export" value="csv" class="btn btn-outline-success">
                        <i class="bi bi-filetype-csv me-1"></i>{{ __('CSV') }}
                    </button>
                </div>
            </div>
        </div>
    </form>
@endif
