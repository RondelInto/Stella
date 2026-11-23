<x-app-layout>
    <x-slot name="title">{{ $sop->title }}</x-slot>
    <x-slot name="header">{{ $sop->sop_code }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('sops.index') }}">{{ __('SOP Library') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $sop->sop_code }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <article class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <div class="small text-muted"><code>{{ $sop->sop_code }}</code> · {{ $sop->category }} · {{ __('Version') }} {{ $sop->version_no }}</div>
                            <h2 class="h4 mb-1">{{ $sop->title }}</h2>
                            @if ($sop->description)
                                <p class="text-muted mb-0">{{ $sop->description }}</p>
                            @endif
                        </div>
                        <a href="{{ route('sops.print', $sop) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-printer me-1"></i>{{ __('Print') }}
                        </a>
                    </div>
                    <hr>
                    <div class="sop-content">{{ $sop->renderedContent() }}</div>
                </div>
            </article>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body small">
                    <dl class="mb-0">
                        @can('manage-sops')
                            <dt class="text-muted fw-normal">{{ __('Status') }}</dt>
                            <dd><x-sop-status-badge :sop="$sop" /></dd>
                        @endcan
                        <dt class="text-muted fw-normal">{{ __('Effective date') }}</dt>
                        <dd>{{ $sop->effective_date?->format('M d, Y') ?? __('Immediately') }}</dd>
                        <dt class="text-muted fw-normal">{{ __('For') }}</dt>
                        <dd>{{ $sop->roles->pluck('name')->join(', ') }}</dd>
                        <dt class="text-muted fw-normal">{{ __('Last updated') }}</dt>
                        <dd class="mb-0">{{ $sop->updated_at->format('M d, Y') }} · {{ $sop->updatedBy?->name ?? $sop->createdBy->name }}</dd>
                    </dl>
                </div>
            </div>

            @can('manage-sops')
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h3 class="h6">{{ __('Manage') }}</h3>
                        <div class="d-grid gap-2">
                            <a href="{{ route('sops.edit', $sop) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>
                            @foreach (['active' => ['Activate', 'btn-outline-success', 'check-circle'], 'draft' => ['Move back to Draft', 'btn-outline-warning', 'pencil-square'], 'archived' => ['Archive', 'btn-outline-secondary', 'archive']] as $status => [$label, $class, $icon])
                                @continue($sop->status === $status)
                                <form method="POST" action="{{ route('sops.status', $sop) }}"
                                      @if ($status !== 'active') data-confirm="{{ __('Change this SOP to :status? Staff will no longer see it.', ['status' => strtoupper($status)]) }}" @endif>
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $status }}">
                                    <button type="submit" class="btn {{ $class }} w-100"><i class="bi bi-{{ $icon }} me-1"></i>{{ __($label) }}</button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h3 class="h6">{{ __('Version History') }}</h3>
                        <ul class="list-unstyled small mb-0">
                            <li class="mb-2"><strong>v{{ $sop->version_no }}</strong> · {{ __('current') }}</li>
                            @forelse ($versions as $version)
                                <li class="mb-2">
                                    <a href="{{ route('sops.versions.show', [$sop, $version]) }}">v{{ $version->version_no }}</a>
                                    · {{ __('replaced') }} {{ $version->created_at->format('M d, Y') }}
                                    <div class="text-muted">{{ __('written by') }} {{ $version->changedBy->name }}</div>
                                </li>
                            @empty
                                <li class="text-muted">{{ __('No earlier versions.') }}</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>
