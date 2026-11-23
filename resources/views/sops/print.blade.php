<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sop->sop_code }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background: #fff; }
        .page { max-width: 800px; margin: 0 auto; }
        @media print {
            .no-print { display: none !important; }
            .page { max-width: none; }
            @page { margin: 15mm; }
        }
    </style>
</head>
<body>
<div class="page p-4">
    <div class="no-print d-flex justify-content-end gap-2 mb-3">
        <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
        <button type="button" class="btn btn-outline-secondary" onclick="window.close()">{{ __('Close') }}</button>
    </div>

    <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-3">
        <div>
            <div class="fw-bold">{{ config('app.name') }}</div>
            <div class="small text-uppercase">{{ __('Standard Operating Procedure') }}</div>
        </div>
        <div class="text-end small">
            <div class="fw-bold">{{ $sop->sop_code }}</div>
            <div>{{ __('Version') }} {{ $sop->version_no }}</div>
            <div>{{ __('Effective') }}: {{ $sop->effective_date?->format('M d, Y') ?? '—' }}</div>
        </div>
    </div>

    <h1 class="h3">{{ $sop->title }}</h1>
    <p class="small text-muted mb-1">{{ __('Category') }}: {{ $sop->category }} · {{ __('For') }}: {{ $sop->roles->pluck('name')->join(', ') }}</p>
    @if ($sop->description)
        <p>{{ $sop->description }}</p>
    @endif
    <hr>
    <div class="sop-content">{{ $sop->renderedContent() }}</div>

    <p class="small text-muted border-top pt-2 mt-4 mb-0">
        {{ __('Printed :date. Always check the system for the latest version.', ['date' => now()->format('M d, Y h:i A')]) }}
    </p>
</div>
</body>
</html>
