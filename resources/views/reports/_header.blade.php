{{--
    Shared report header: period filter, extra filters (slot), Print and CSV buttons, and a print-only title.
    Variables: $title, $route, $from, $to, optional $extraFilters (HTML), $subtitle.
--}}
<div class="print-only mb-3">
    <div class="fw-bold">{{ config('app.name') }}</div>
    <h1 class="h4 mb-0">{{ $title }}</h1>
    <div class="small">
        {{ $from->format('M d, Y') }} – {{ $to->format('M d, Y') }}
        @isset($subtitle) · {{ $subtitle }} @endisset
        · {{ __('Printed :date by :name', ['date' => now()->format('M d, Y h:i A'), 'name' => Auth::user()->name]) }}
    </div>
</div>

<nav aria-label="breadcrumb" class="no-print">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">{{ __('Reports') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
    </ol>
</nav>

<form method="GET" action="{{ route($route) }}" class="card border-0 shadow-sm mb-3 no-print">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label for="from" class="form-label small mb-1">{{ __('From') }}</label>
                <input type="date" id="from" name="from" class="form-control" value="{{ $from->toDateString() }}" max="{{ today()->toDateString() }}" required>
            </div>
            <div class="col-6 col-md-2">
                <label for="to" class="form-label small mb-1">{{ __('To') }}</label>
                <input type="date" id="to" name="to" class="form-control" value="{{ $to->toDateString() }}" max="{{ today()->toDateString() }}" required>
            </div>
            {{ $extraFilters ?? '' }}
            <div class="col-12 col-md d-flex flex-wrap gap-2 justify-content-md-end">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i>{{ __('Apply') }}</button>
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
