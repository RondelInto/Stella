<x-app-layout>
    <x-slot name="title">{{ $transfer->reference_no }}</x-slot>
    <x-slot name="header">{{ __('Transfer :ref', ['ref' => $transfer->reference_no]) }}</x-slot>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('stock-transfers.index') }}">{{ __('Stock Transfers') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $transfer->reference_no }}</li>
        </ol>
    </nav>

    {{-- Summary + actions --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h2 class="h4 mb-0">{{ $transfer->reference_no }}</h2>
                        <x-transfer-status-badge :status="$transfer->status" />
                    </div>
                    <div class="fs-6">
                        {{ $transfer->sourceLocation->name }}
                        <i class="bi bi-arrow-right mx-1"></i>
                        <strong>{{ $transfer->destinationLocation->name }}</strong>
                    </div>
                    <div class="small text-muted">{{ __('Transfer date') }}: {{ $transfer->transfer_date->format('M d, Y') }}</div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('stock-transfers.print', $transfer) }}" target="_blank" class="btn btn-outline-secondary">
                        <i class="bi bi-printer me-1"></i>{{ __('Print Slip') }}
                    </a>
                    @can('update', $transfer)
                        <a href="{{ route('stock-transfers.edit', $transfer) }}" class="btn btn-outline-primary">
                            <i class="bi bi-pencil me-1"></i>{{ __('Edit') }}
                        </a>
                    @endcan
                    @can('release', $transfer)
                        <form method="POST" action="{{ route('stock-transfers.release', $transfer) }}"
                              data-confirm="{{ __('Release this transfer? The items will be deducted from commissary stock. A released transfer cannot be cancelled.') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary"><i class="bi bi-truck me-1"></i>{{ __('Release') }}</button>
                        </form>
                    @endcan
                    @can('cancel', $transfer)
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                            <i class="bi bi-x-circle me-1"></i>{{ __('Cancel Transfer') }}
                        </button>
                    @endcan
                </div>
            </div>

            {{-- Who did what, when --}}
            <div class="row g-3 small mt-2">
                <div class="col-6 col-lg-3">
                    <div class="text-muted">{{ __('Prepared by') }}</div>
                    <div>{{ $transfer->preparedBy->name }}</div>
                    <div class="text-muted">{{ $transfer->created_at->format('M d, Y h:i A') }}</div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="text-muted">{{ __('Released by') }}</div>
                    <div>{{ $transfer->releasedBy?->name ?? '—' }}</div>
                    <div class="text-muted">{{ $transfer->released_at?->format('M d, Y h:i A') }}</div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="text-muted">{{ __('Received by') }}</div>
                    <div>{{ $transfer->receivedBy?->name ?? '—' }}</div>
                    <div class="text-muted">{{ $transfer->received_at?->format('M d, Y h:i A') }}</div>
                </div>
                @if ($transfer->remarks)
                    <div class="col-6 col-lg-3">
                        <div class="text-muted">{{ __('Remarks') }}</div>
                        <div style="white-space: pre-line">{{ $transfer->remarks }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($transfer->hasDiscrepancy())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-diamond me-1"></i>
            {{ __('Some items arrived short. The differences are recorded as transfer discrepancies.') }}
        </div>
    @endif

    @can('receive', $transfer)
        {{-- ============ RECEIVE FORM (destination branch) ============ --}}
        <form method="POST" action="{{ route('stock-transfers.receive', $transfer) }}"
              data-confirm="{{ __('Confirm receipt? The received quantities will be added to your branch stock. This cannot be undone.') }}">
            @csrf
            <div class="card border-primary shadow-sm">
                <div class="card-header bg-primary-subtle fw-semibold">
                    <i class="bi bi-box-arrow-in-down me-1"></i>{{ __('Confirm Receipt') }}
                </div>
                <div class="card-body pb-0">
                    <p class="small text-muted">
                        {{ __('Check each item and enter the quantity that actually arrived. If it is different from what was sent, give a reason. Received cannot be more than sent.') }}
                    </p>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Item') }}</th>
                                <th class="text-end">{{ __('Sent') }}</th>
                                <th style="width: 200px">{{ __('Received') }}</th>
                                <th>{{ __('Reason if different') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transfer->details as $detail)
                                @php
                                    $sent = rtrim(rtrim($detail->quantity_sent, '0'), '.');
                                @endphp
                                <tr>
                                    <td>{{ $detail->inventoryItem->item_code }} — {{ $detail->inventoryItem->name }}</td>
                                    <td class="text-end"><x-qty :value="$detail->quantity_sent" :unit="$detail->inventoryItem->unit_of_measure" /></td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.001" min="0" max="{{ $sent }}"
                                                   name="received[{{ $detail->id }}][quantity_received]"
                                                   value="{{ old('received.'.$detail->id.'.quantity_received', $sent) }}"
                                                   class="form-control @error('received.'.$detail->id.'.quantity_received') is-invalid @enderror" required>
                                            <span class="input-group-text">{{ $detail->inventoryItem->unit_of_measure }}</span>
                                        </div>
                                        <x-input-error :messages="$errors->get('received.'.$detail->id.'.quantity_received')" />
                                    </td>
                                    <td>
                                        <input type="text" maxlength="255" name="received[{{ $detail->id }}][remarks]"
                                               value="{{ old('received.'.$detail->id.'.remarks') }}"
                                               class="form-control form-control-sm @error('received.'.$detail->id.'.remarks') is-invalid @enderror"
                                               placeholder="{{ __('e.g. 2 trays spoiled') }}">
                                        <x-input-error :messages="$errors->get('received.'.$detail->id.'.remarks')" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white text-end">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-all me-1"></i>{{ __('Confirm Receipt') }}</button>
                </div>
            </div>
        </form>
    @else
        {{-- ============ ITEM LIST ============ --}}
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Item') }}</th>
                            @if ($transfer->isPending())
                                <th class="text-end">{{ __('Commissary Stock') }}</th>
                            @endif
                            <th class="text-end">{{ __('Sent') }}</th>
                            <th class="text-end">{{ __('Received') }}</th>
                            <th class="text-end">{{ __('Difference') }}</th>
                            <th>{{ __('Remarks') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($transfer->details as $detail)
                            @php
                                $unit = $detail->inventoryItem->unit_of_measure;
                                $diff = $detail->discrepancy();
                                $short = $transfer->isPending() && (float) ($available[$detail->inventory_item_id] ?? 0) < (float) $detail->quantity_sent;
                            @endphp
                            <tr class="{{ $diff !== null && $diff != 0 ? 'table-warning' : '' }}">
                                <td>
                                    <a href="{{ route('inventory-items.show', $detail->inventoryItem) }}" class="text-decoration-none">{{ $detail->inventoryItem->name }}</a>
                                    <div class="small text-muted"><code>{{ $detail->inventoryItem->item_code }}</code></div>
                                </td>
                                @if ($transfer->isPending())
                                    <td class="text-end {{ $short ? 'text-danger fw-semibold' : 'text-muted' }}">
                                        <x-qty :value="$available[$detail->inventory_item_id] ?? 0" :unit="$unit" />
                                        @if ($short)
                                            <div class="small">{{ __('Not enough to release') }}</div>
                                        @endif
                                    </td>
                                @endif
                                <td class="text-end"><x-qty :value="$detail->quantity_sent" :unit="$unit" /></td>
                                <td class="text-end">
                                    @if ($detail->quantity_received !== null)
                                        <x-qty :value="$detail->quantity_received" :unit="$unit" />
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($diff !== null)
                                        <x-variance :value="$diff" :unit="$unit" />
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small">{{ $detail->remarks }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endcan

    {{-- Cancel modal --}}
    @can('cancel', $transfer)
        <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('stock-transfers.cancel', $transfer) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title h6" id="cancelModalLabel">{{ __('Cancel transfer :ref?', ['ref' => $transfer->reference_no]) }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">{{ __('No stock has been moved yet, so nothing needs to be reversed.') }}</p>
                        <label for="reason" class="form-label">{{ __('Reason (optional)') }}</label>
                        <input type="text" id="reason" name="reason" maxlength="255" class="form-control">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Keep Transfer') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('Cancel Transfer') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</x-app-layout>
