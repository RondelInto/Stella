<x-app-layout>
    <x-slot name="title">{{ __('Locations') }}</x-slot>
    <x-slot name="header">{{ __('Locations') }}</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">{{ __('The central commissary and branches of Stella\'s Cuisine.') }}</p>
        <a href="{{ route('locations.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>{{ __('Add Branch') }}
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Address') }}</th>
                        <th>{{ __('Contact No.') }}</th>
                        <th class="text-center">{{ __('Active Employees') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($locations as $location)
                        <tr>
                            <td class="fw-semibold">{{ $location->name }}</td>
                            <td>
                                @if ($location->isCommissary())
                                    <span class="badge text-bg-warning"><i class="bi bi-building me-1"></i>{{ __('Commissary') }}</span>
                                @else
                                    <span class="badge text-bg-light border"><i class="bi bi-shop me-1"></i>{{ __('Branch') }}</span>
                                @endif
                            </td>
                            <td>{{ $location->address ?: '—' }}</td>
                            <td>{{ $location->contact_no ?: '—' }}</td>
                            <td class="text-center">
                                <a href="{{ route('users.index', ['location' => $location->id, 'status' => 'active']) }}">
                                    {{ $location->active_users_count }}
                                </a>
                            </td>
                            <td>
                                @if ($location->status === 'active')
                                    <span class="badge text-bg-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('locations.edit', $location) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> {{ __('Edit') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">{{ __('No locations found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
