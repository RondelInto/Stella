<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $transfer->reference_no }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background: #fff; }
        .slip { max-width: 800px; margin: 0 auto; }
        .signature { border-top: 1px solid #000; margin-top: 3rem; padding-top: .25rem; }
        @media print {
            .no-print { display: none !important; }
            .slip { max-width: none; }
            @page { margin: 12mm; }
        }
    </style>
</head>
<body>
<div class="slip p-4">
    <div class="no-print d-flex justify-content-end gap-2 mb-3">
        <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
        <button type="button" class="btn btn-outline-secondary" onclick="window.close()">{{ __('Close') }}</button>
    </div>

    <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-3">
        <div>
            <div class="fs-5 fw-bold">{{ config('app.name') }}</div>
            <div class="text-uppercase small fw-semibold">{{ __('Stock Transfer Slip') }}</div>
        </div>
        <div class="text-end">
            <div class="fs-5 fw-bold">{{ $transfer->reference_no }}</div>
            <div class="small">{{ __('Status') }}: {{ ucfirst($transfer->status) }}</div>
        </div>
    </div>

    <table class="table table-sm table-borderless small mb-3">
        <tr>
            <th style="width: 18%">{{ __('From') }}</th><td>{{ $transfer->sourceLocation->name }}</td>
            <th style="width: 18%">{{ __('Transfer Date') }}</th><td>{{ $transfer->transfer_date->format('M d, Y') }}</td>
        </tr>
        <tr>
            <th>{{ __('To') }}</th><td>{{ $transfer->destinationLocation->name }}</td>
            <th>{{ __('Printed') }}</th><td>{{ now()->format('M d, Y h:i A') }}</td>
        </tr>
        @if ($transfer->remarks)
            <tr><th>{{ __('Remarks') }}</th><td colspan="3" style="white-space: pre-line">{{ $transfer->remarks }}</td></tr>
        @endif
    </table>

    <table class="table table-bordered table-sm small">
        <thead>
            <tr>
                <th style="width: 5%">#</th>
                <th style="width: 15%">{{ __('Code') }}</th>
                <th>{{ __('Item') }}</th>
                <th style="width: 10%">{{ __('Unit') }}</th>
                <th class="text-end" style="width: 13%">{{ __('Qty Sent') }}</th>
                <th class="text-end" style="width: 13%">{{ __('Qty Received') }}</th>
                <th style="width: 18%">{{ __('Remarks') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transfer->details as $i => $detail)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $detail->inventoryItem->item_code }}</td>
                    <td>{{ $detail->inventoryItem->name }}</td>
                    <td>{{ $detail->inventoryItem->unit_of_measure }}</td>
                    <td class="text-end"><x-qty :value="$detail->quantity_sent" /></td>
                    <td class="text-end">
                        @if ($detail->quantity_received !== null)
                            <x-qty :value="$detail->quantity_received" />
                        @endif
                    </td>
                    <td>{{ $detail->remarks }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="row small mt-4">
        <div class="col-4">
            <div class="signature">{{ __('Prepared by') }}: {{ $transfer->preparedBy->name }}</div>
        </div>
        <div class="col-4">
            <div class="signature">{{ __('Released by') }}: {{ $transfer->releasedBy?->name }}</div>
            <div class="text-muted">{{ __('Date/Time') }}: {{ $transfer->released_at?->format('M d, Y h:i A') }}</div>
        </div>
        <div class="col-4">
            <div class="signature">{{ __('Received by') }}: {{ $transfer->receivedBy?->name }}</div>
            <div class="text-muted">{{ __('Date/Time') }}: {{ $transfer->received_at?->format('M d, Y h:i A') }}</div>
        </div>
    </div>
</div>
</body>
</html>
