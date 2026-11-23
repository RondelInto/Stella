<x-app-layout>
    <x-slot name="title">{{ __('Import Items') }}</x-slot>
    <x-slot name="header">{{ __('Import Items from CSV') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('inventory-items.index') }}">{{ __('Items') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('Import') }}</li>
        </ol>
    </nav>

    {{-- Row-by-row errors from the last upload --}}
    @if (session('import_errors'))
        @php($importErrors = session('import_errors'))
        <div class="card border-danger shadow-sm mb-4">
            <div class="card-header bg-danger-subtle text-danger-emphasis fw-semibold">
                <i class="bi bi-exclamation-octagon me-1"></i>{{ __('Errors found — nothing was imported') }}
            </div>
            <div class="table-responsive" style="max-height: 420px">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="text-nowrap">{{ __('Row') }}</th>
                            <th>{{ __('Item Code') }}</th>
                            <th>{{ __('Problem') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_slice($importErrors, 0, 200) as $error)
                            <tr>
                                <td>{{ $error['row'] }}</td>
                                <td><code>{{ $error['item_code'] ?: '—' }}</code></td>
                                <td class="small">
                                    @foreach ($error['messages'] as $message)
                                        <div>{{ $message }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($importErrors) > 200)
                <div class="card-footer small text-muted">
                    {{ __('Showing the first 200 of :count rows with errors.', ['count' => count($importErrors)]) }}
                </div>
            @endif
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <form method="POST" action="{{ route('inventory-items.import.store') }}" enctype="multipart/form-data"
                  class="card border-0 shadow-sm">
                @csrf
                <div class="card-body p-4">
                    <x-input-label for="file" :value="__('CSV File')" />
                    <input type="file" id="file" name="file" accept=".csv,text/csv"
                           class="form-control @error('file') is-invalid @enderror" required>
                    <div class="form-text">{{ __('Maximum 2 MB. In Excel: File > Save As > "CSV UTF-8 (Comma delimited)".') }}</div>
                    <x-input-error :messages="$errors->get('file')" />
                </div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('inventory-items.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <x-primary-button><i class="bi bi-upload me-1"></i>{{ __('Import') }}</x-primary-button>
                </div>
            </form>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6">{{ __('File format') }}</h2>
                    <p class="small text-muted">{{ __('The first row must contain exactly these column headers:') }}</p>
                    <p><code>{{ implode(',', $headers) }}</code></p>

                    <ul class="small mb-3">
                        <li><strong>category</strong>: {{ __('must match an existing category name.') }}</li>
                        <li><strong>item_type</strong>: <code>ingredient</code>, <code>in_preparation</code>, {{ __('or') }} <code>finished_product</code>.</li>
                        <li><strong>unit_cost / reorder_level</strong>: {{ __('numbers only, no ₱ sign or commas.') }}</li>
                        <li>{{ __('Item codes must be new. Existing items are edited on the item page.') }}</li>
                        <li>{{ __('Stock quantities are not imported. Opening stock is set with the first stock count.') }}</li>
                    </ul>

                    <div class="alert alert-light border small mb-3">
                        <i class="bi bi-shield-check me-1"></i>
                        {{ __('If any row has an error, nothing is imported. Fix the listed rows and upload the whole file again.') }}
                    </div>

                    <a href="{{ route('inventory-items.import.template') }}" class="btn btn-outline-primary">
                        <i class="bi bi-download me-1"></i>{{ __('Download Empty Template') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
