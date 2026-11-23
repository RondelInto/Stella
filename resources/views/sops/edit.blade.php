<x-app-layout>
    <x-slot name="title">{{ __('Edit SOP') }}</x-slot>
    <x-slot name="header">{{ __('Edit :code', ['code' => $sop->sop_code]) }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('sops.index') }}">{{ __('SOP Library') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('sops.show', $sop) }}">{{ $sop->sop_code }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('Edit') }}</li>
        </ol>
    </nav>

    @if ($sop->isActive())
        <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1"></i>
            {{ __('This SOP is active. Changing its title, description, procedure, or effective date saves the current wording as version :v and creates version :next.', ['v' => $sop->version_no, 'next' => $sop->version_no + 1]) }}
        </div>
    @endif

    <form method="POST" action="{{ route('sops.update', $sop) }}" class="card border-0 shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body p-4">@include('sops._form')</div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('sops.show', $sop) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
        </div>
    </form>
</x-app-layout>
