<x-app-layout>
    <x-slot name="title">{{ $sop->sop_code }} v{{ $version->version_no }}</x-slot>
    <x-slot name="header">{{ $sop->sop_code }} · {{ __('Version :v', ['v' => $version->version_no]) }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('sops.index') }}">{{ __('SOP Library') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('sops.show', $sop) }}">{{ $sop->sop_code }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">v{{ $version->version_no }}</li>
        </ol>
    </nav>

    <div class="alert alert-warning small">
        <i class="bi bi-clock-history me-1"></i>
        {{ __('This is an old version kept for the record. The current version is :v.', ['v' => $sop->version_no]) }}
        <a href="{{ route('sops.show', $sop) }}">{{ __('Open current version') }}</a>
    </div>

    <article class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="small text-muted">
                {{ __('Version') }} {{ $version->version_no }} · {{ __('written by') }} {{ $version->changedBy->name }}
                · {{ __('effective') }} {{ $version->effective_date?->format('M d, Y') ?? __('immediately') }}
                · {{ __('replaced on') }} {{ $version->created_at->format('M d, Y h:i A') }}
            </div>
            <h2 class="h4 mb-1">{{ $version->title }}</h2>
            @if ($version->description)
                <p class="text-muted">{{ $version->description }}</p>
            @endif
            <hr>
            <div class="sop-content">{{ $version->renderedContent() }}</div>
        </div>
    </article>
</x-app-layout>
