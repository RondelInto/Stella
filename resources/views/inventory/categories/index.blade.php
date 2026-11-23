<x-app-layout>
    <x-slot name="title">{{ __('Inventory Categories') }}</x-slot>
    <x-slot name="header">{{ __('Inventory Categories') }}</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">{{ __('Groups used to organize inventory items.') }}</p>
        <a href="{{ route('inventory-categories.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>{{ __('Add Category') }}
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Description') }}</th>
                        <th class="text-center">{{ __('Items') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td class="fw-semibold">{{ $category->name }}</td>
                            <td>{{ $category->description ?: '—' }}</td>
                            <td class="text-center">
                                <a href="{{ route('inventory-items.index', ['category' => $category->id]) }}">{{ $category->inventory_items_count }}</a>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('inventory-categories.edit', $category) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> {{ __('Edit') }}
                                </a>
                                @if ($category->inventory_items_count === 0)
                                    <form method="POST" action="{{ route('inventory-categories.destroy', $category) }}" class="d-inline"
                                          data-confirm="{{ __('Delete category :name?', ['name' => $category->name]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No categories yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
