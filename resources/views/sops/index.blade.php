<x-app-layout>
    <x-slot name="title">{{ __('SOP Library') }}</x-slot>
    <x-slot name="header">{{ __('Standard Operating Procedures') }}</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">
            @if ($canManage)
                {{ __('All procedures, including drafts and archived ones.') }}
            @else
                {{ __('Procedures assigned to your role.') }}
            @endif
        </p>
        @if ($canManage)
            <a href="{{ route('sops.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('New SOP') }}</a>
        @endif
    </div>

    <form method="GET" action="{{ route('sops.index') }}" class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="search" class="form-label small mb-1">{{ __('Search') }}</label>
                    <input type="search" id="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}"
                           placeholder="{{ __('Title, code, or description') }}">
                </div>
                <div class="col-6 col-md-3">
                    <label for="category" class="form-label small mb-1">{{ __('Category') }}</label>
                    <select id="category" name="category" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($canManage)
                    <div class="col-6 col-md-2">
                        <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">{{ __('All') }}</option>
                            @foreach (['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="role" class="form-label small mb-1">{{ __('Role') }}</label>
                        <select id="role" name="role" class="form-select">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected(($filters['role'] ?? '') == $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-6 col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1" title="{{ __('Filter') }}"><i class="bi bi-funnel"></i></button>
                    <a href="{{ route('sops.index') }}" class="btn btn-outline-secondary" title="{{ __('Clear filters') }}"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Category') }}</th>
                        @if ($canManage)
                            <th>{{ __('Roles') }}</th>
                        @endif
                        <th class="text-center">{{ __('Version') }}</th>
                        <th>{{ __('Effective') }}</th>
                        @if ($canManage)
                            <th>{{ __('Status') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sops as $sop)
                        <tr>
                            <td class="text-nowrap"><code>{{ $sop->sop_code }}</code></td>
                            <td>
                                <a href="{{ route('sops.show', $sop) }}" class="fw-semibold text-decoration-none">{{ $sop->title }}</a>
                                @if ($sop->description)
                                    <div class="small text-muted">{{ $sop->description }}</div>
                                @endif
                            </td>
                            <td>{{ $sop->category }}</td>
                            @if ($canManage)
                                <td class="small">{{ $sop->roles->pluck('name')->join(', ') }}</td>
                            @endif
                            <td class="text-center">v{{ $sop->version_no }}</td>
                            <td class="text-nowrap small">{{ $sop->effective_date?->format('M d, Y') ?? '—' }}</td>
                            @if ($canManage)
                                <td><x-sop-status-badge :sop="$sop" /></td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 7 : 5 }}" class="text-center text-muted py-4">
                                {{ $canManage ? __('No SOPs yet.') : __('No procedures are assigned to your role yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($sops->hasPages())
            <div class="card-footer bg-white">{{ $sops->links() }}</div>
        @endif
    </div>
</x-app-layout>
