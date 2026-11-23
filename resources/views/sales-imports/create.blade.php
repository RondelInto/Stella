<x-app-layout>
    <x-slot name="title">{{ __('Import POS Sales') }}</x-slot>
    <x-slot name="header">{{ __('Import POS Sales from CSV') }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('sales-imports.index') }}">{{ __('POS Sales Imports') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('Import') }}</li>
        </ol>
    </nav>

    {{-- Row-by-row errors from the last upload --}}
    @if (session('import_errors'))
        @php
            $importErrors = session('import_errors');
        @endphp
        <div class="card border-danger shadow-sm mb-4">
            <div class="card-header bg-danger-subtle text-danger-emphasis fw-semibold">
                <i class="bi bi-exclamation-octagon me-1"></i>{{ __('Problems found. Nothing was imported.') }}
            </div>
            <div class="table-responsive" style="max-height: 420px">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="text-nowrap">{{ __('Row') }}</th>
                            <th>{{ __('Receipt No.') }}</th>
                            <th>{{ __('Problem') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_slice($importErrors, 0, 200) as $error)
                            <tr>
                                <td>{{ $error['row'] }}</td>
                                <td><code>{{ $error['receipt_no'] ?: '—' }}</code></td>
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
                    {{ __('Showing the first 200 of :count rows with problems.', ['count' => count($importErrors)]) }}
                </div>
            @endif
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <form method="POST" action="{{ route('sales-imports.store') }}" enctype="multipart/form-data"
                  class="card border-0 shadow-sm">
                @csrf
                <div class="card-body p-4">
                    <div class="mb-3">
                        <x-input-label for="location_id" :value="__('Location (whose POS the file came from)')" />
                        <select id="location_id" name="location_id" class="form-select @error('location_id') is-invalid @enderror" required>
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($locations as $loc)
                                <option value="{{ $loc->id }}" @selected((int) old('location_id') === $loc->id)>
                                    {{ $loc->name }}@if ($loc->status !== 'active') ({{ __('inactive') }})@endif
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('location_id')" />
                    </div>

                    <x-input-label for="file" :value="__('CSV File')" />
                    <input type="file" id="file" name="file" accept=".csv,text/csv"
                           class="form-control @error('file') is-invalid @enderror" required>
                    <div class="form-text">
                        {{ __('Maximum 5 MB and :rows rows. In Excel: File > Save As > "CSV UTF-8 (Comma delimited)".', ['rows' => number_format($maxRows)]) }}
                    </div>
                    <x-input-error :messages="$errors->get('file')" />
                </div>
                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('sales-imports.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <x-primary-button><i class="bi bi-upload me-1"></i>{{ __('Import') }}</x-primary-button>
                </div>
            </form>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h6">{{ __('File format (our template)') }}</h2>
                    <p class="small text-muted">{{ __('One row = one item sold on one receipt. The first row must contain exactly these column headers:') }}</p>
                    <p><code>{{ implode(',', $headers) }}</code></p>

                    <ul class="small mb-3">
                        <li><strong>sale_datetime</strong>: <code>2026-11-03 14:30</code> {{ __('(Excel\'s 11/03/2026 14:30 also works).') }}</li>
                        <li><strong>product_code</strong>: {{ __('optional. Matched to the product\'s POS code first, then by product name.') }}</li>
                        <li><strong>quantity</strong>: {{ __('more than 0. Refunds and voids are not imported.') }}</li>
                        <li><strong>unit_price / discount / net_amount</strong>: {{ __('numbers only, no ₱ sign or commas. discount and net_amount may be blank; a blank net amount is quantity × unit price − discount.') }}</li>
                        <li>{{ __('Products not yet in the system are still imported and can be linked later.') }}</li>
                    </ul>

                    <div class="alert alert-light border small mb-3">
                        <i class="bi bi-shield-check me-1"></i>
                        {{ __('If any row has a problem, nothing is imported. A file whose dates overlap an earlier import for the same location is also rejected, so sales are never counted twice.') }}
                    </div>

                    <a href="{{ route('sales-imports.template') }}" class="btn btn-outline-primary">
                        <i class="bi bi-download me-1"></i>{{ __('Download Empty Template') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
