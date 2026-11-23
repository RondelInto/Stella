<x-app-layout>
    <x-slot name="title">{{ __('Import #:id', ['id' => $batch->id]) }}</x-slot>
    <x-slot name="header">{{ __('POS Sales Import #:id', ['id' => $batch->id]) }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('sales-imports.index') }}">{{ __('POS Sales Imports') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">#{{ $batch->id }}</li>
        </ol>
    </nav>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-lg-3">
                    <div class="small text-muted">{{ __('Location') }}</div>
                    <div class="fw-semibold">{{ $batch->location->name }}</div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="small text-muted">{{ __('Sales Period') }}</div>
                    <div class="fw-semibold">{{ $batch->period_start->format('M d, Y') }} – {{ $batch->period_end->format('M d, Y') }}</div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="small text-muted">{{ __('File') }}</div>
                    <div class="fw-semibold text-break">{{ $batch->file_name }}</div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="small text-muted">{{ __('Imported') }}</div>
                    <div class="fw-semibold">{{ $batch->imported_at->format('M d, Y g:i A') }}</div>
                    <div class="small text-muted">{{ $batch->importedBy->name }}</div>
                </div>
            </div>
            <hr>
            <div class="row g-3 text-center">
                <div class="col-6 col-lg-3"><div class="small text-muted">{{ __('Rows') }}</div><div class="fs-5 fw-semibold">{{ number_format($totals->lines_count) }}</div></div>
                <div class="col-6 col-lg-3"><div class="small text-muted">{{ __('Receipts') }}</div><div class="fs-5 fw-semibold">{{ number_format($totals->receipts) }}</div></div>
                <div class="col-6 col-lg-3"><div class="small text-muted">{{ __('Net Sales') }}</div><div class="fs-5 fw-semibold">₱{{ number_format((float) $totals->net, 2) }}</div></div>
                <div class="col-6 col-lg-3">
                    <div class="small text-muted">{{ __('Unmatched Rows') }}</div>
                    <div class="fs-5 fw-semibold {{ $totals->unmatched ? 'text-warning' : 'text-success' }}">{{ number_format($totals->unmatched) }}</div>
                </div>
            </div>
        </div>
    </div>

    @if ($totals->unmatched)
        <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Some products in this file are not in the system yet. Sales analysis skips them until they are linked.') }}</span>
            <a href="{{ route('sales-imports.unmatched') }}" class="btn btn-sm btn-warning">{{ __('Link Products') }}</a>
        </div>
    @endif

    <h2 class="h6 text-uppercase text-muted small">{{ __('Sales by Product') }}</h2>
    <div class="card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th class="text-end">{{ __('Quantity Sold') }}</th>
                        <th class="text-end">{{ __('Receipts') }}</th>
                        <th class="text-end">{{ __('Net Sales') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($products as $p)
                        <tr>
                            <td>
                                @if ($p->product_id)
                                    {{ $p->product_name }}
                                @else
                                    {{ $p->file_name }} <span class="badge text-bg-warning">{{ __('Unmatched') }}</span>
                                @endif
                            </td>
                            <td class="text-end"><x-qty :value="$p->quantity" /></td>
                            <td class="text-end">{{ number_format($p->receipts) }}</td>
                            <td class="text-end">₱{{ number_format((float) $p->net, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <p class="small text-muted mb-0">{{ __('The original rows of the file are kept unchanged for traceability.') }}</p>
        @if ($batch->canBeDeleted())
            <form method="POST" action="{{ route('sales-imports.destroy', $batch) }}"
                  data-confirm="{{ __('Delete import #:id and all :rows sales rows? This cannot be undone.', ['id' => $batch->id, 'rows' => $batch->total_records]) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>{{ __('Delete This Import') }}</button>
            </form>
        @else
            <span class="small text-muted"><i class="bi bi-lock me-1"></i>{{ __('Used in a sales analysis, so it cannot be deleted.') }}</span>
        @endif
    </div>
</x-app-layout>
