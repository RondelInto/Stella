<?php

namespace App\Services;

use App\Models\ImportedPosSale;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesImportBatch;
use App\Models\User;
use App\Support\CsvReader;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Imports sales EXPORTED from a location's own POS system (module 7).
 * This system never connects to, changes or replaces the POS. It only reads the exported file.
 *
 * The owner's real POS export format is not known yet, so this reads OUR template (decision PI1).
 * When the real file arrives, only HEADERS / the column mapping in this class should need to change.
 *
 * Team decisions:
 *  - PI2: one row = one item sold on one receipt.
 *  - PI3: one file = one location, picked on the upload form.
 *  - PI4: rows whose product is not in the system are imported as "unmatched" (product_id NULL)
 *         and linked to a product later by Admin.
 *  - PI5: ALL-OR-NOTHING. Any row error = nothing is imported.
 *  - PI6: a file whose dates overlap an earlier import for the same location is rejected.
 *  - PI8: CSV only.
 * The original row is always kept unchanged in raw_data (source data preservation).
 */
class PosSalesImporter
{
    public const HEADERS = [
        'receipt_no', 'sale_datetime', 'product_code', 'product_name',
        'quantity', 'unit_price', 'discount', 'net_amount',
    ];

    public const MAX_ROWS = 20000;

    /** Accepted date-time formats: our template's format plus Excel's usual US-style display. */
    public const DATE_FORMATS = [
        'Y-m-d H:i:s', 'Y-m-d H:i',
        'm/d/Y H:i:s', 'm/d/Y H:i',
        'm/d/Y g:i:s A', 'm/d/Y g:i A',
    ];

    private const INSERT_CHUNK = 500;

    /**
     * @return array{batch: ?SalesImportBatch, unmatched: int, errors: array<int, array{row: int, receipt_no: string, messages: array<int, string>}>}
     */
    public function import(string $path, string $fileName, Location $location, User $user): array
    {
        $csv = CsvReader::read($path, self::HEADERS, self::MAX_ROWS);
        if (isset($csv['error'])) {
            return $this->fail([['row' => 1, 'receipt_no' => '', 'messages' => [$csv['error']]]]);
        }

        [$byCode, $byName] = $this->productLookups();
        $now = CarbonImmutable::now();

        $errors = [];
        $records = [];

        foreach ($csv['rows'] as $rowNumber => $row) {
            // Blank optional cells become NULL so "nullable" rules apply.
            foreach (['product_code', 'discount', 'net_amount'] as $optional) {
                $row[$optional] = $row[$optional] === '' ? null : $row[$optional];
            }

            $messages = Validator::make($row, $this->rules(), $this->messages(), $this->attributes())->errors()->all();

            $saleAt = null;
            if ($row['sale_datetime'] === '') {
                $messages[] = 'The sale date and time is required.';
            } elseif (! $saleAt = $this->parseDateTime($row['sale_datetime'])) {
                $messages[] = "The sale date \"{$row['sale_datetime']}\" is not valid. Use YYYY-MM-DD HH:MM, e.g. 2026-11-03 14:30.";
            } elseif ($saleAt->greaterThan($now)) {
                $messages[] = 'The sale date is in the future.';
            }

            if (! $messages) {
                $netCents = $row['net_amount'] !== null
                    ? $this->toCents($row['net_amount'])
                    : $this->lineTotalCents($row['quantity'], $row['unit_price'], $row['discount'] ?? '0');

                if ($netCents < 0) {
                    $messages[] = 'The discount is larger than the line total.';
                }
            }

            if ($messages) {
                $errors[] = ['row' => $rowNumber, 'receipt_no' => $row['receipt_no'], 'messages' => $messages];

                continue;
            }

            $records[] = [
                'product_id' => $this->matchProduct($row['product_code'], $row['product_name'], $byCode, $byName),
                'pos_product_code' => $row['product_code'],
                'pos_product_name' => $row['product_name'],
                'pos_receipt_no' => $row['receipt_no'],
                'sale_datetime' => $saleAt->format('Y-m-d H:i:s'),
                'quantity_sold' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'discount_amount' => $row['discount'] ?? '0',
                'net_amount' => number_format($netCents / 100, 2, '.', ''),
                'raw_data' => json_encode($csv['raw'][$rowNumber], JSON_UNESCAPED_UNICODE),
            ];
        }

        if ($errors) {
            return $this->fail($errors);
        }

        $dates = array_column($records, 'sale_datetime');
        $periodStart = substr(min($dates), 0, 10);
        $periodEnd = substr(max($dates), 0, 10);

        return DB::transaction(function () use ($location, $user, $fileName, $periodStart, $periodEnd, $records) {
            // Lock the location so two imports for it cannot pass the overlap check at the same time.
            Location::whereKey($location->id)->lockForUpdate()->first();

            if ($overlap = $this->overlappingBatch($location, $periodStart, $periodEnd)) {
                return $this->fail([['row' => 1, 'receipt_no' => '', 'messages' => [sprintf(
                    'These sales (%s to %s) overlap import #%d "%s" (%s to %s) for %s. Sales would be counted twice. '
                    .'If the earlier import was wrong, delete it first.',
                    $periodStart, $periodEnd, $overlap->id, $overlap->file_name,
                    $overlap->period_start->toDateString(), $overlap->period_end->toDateString(), $location->name,
                )]]]);
            }

            $batch = SalesImportBatch::create([
                'location_id' => $location->id,
                'imported_by' => $user->id,
                'file_name' => Str::limit($fileName, 250, ''),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'imported_at' => now(),
                'total_records' => count($records),
                'status' => 'completed',
            ]);

            $timestamp = now();
            foreach (array_chunk($records, self::INSERT_CHUNK) as $chunk) {
                ImportedPosSale::insert(array_map(fn ($r) => $r + [
                    'sales_import_batch_id' => $batch->id,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ], $chunk));
            }

            return [
                'batch' => $batch,
                'unmatched' => count(array_filter($records, fn ($r) => $r['product_id'] === null)),
                'errors' => [],
            ];
        });
    }

    private function fail(array $errors): array
    {
        return ['batch' => null, 'unmatched' => 0, 'errors' => $errors];
    }

    private function rules(): array
    {
        return [
            'receipt_no' => ['required', 'string', 'max:100'],
            'product_code' => ['nullable', 'string', 'max:50'],
            'product_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:100000', 'decimal:0,3'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:10000000', 'decimal:0,2'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:10000000', 'decimal:0,2'],
            'net_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
        ];
    }

    private function messages(): array
    {
        return [
            'numeric' => 'The :attribute must be a number (no ₱ sign or commas).',
            'quantity.gt' => 'The quantity must be more than 0. Refunds and voids are not imported.',
        ];
    }

    private function attributes(): array
    {
        return [
            'receipt_no' => 'receipt number',
            'product_code' => 'product code',
            'product_name' => 'product name',
            'unit_price' => 'unit price',
            'net_amount' => 'net amount',
        ];
    }

    private function parseDateTime(string $value): ?CarbonImmutable
    {
        foreach (self::DATE_FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            $problems = \DateTimeImmutable::getLastErrors();

            // getLastErrors() also catches impossible dates such as 2026-02-30.
            if ($date !== false && ($problems === false || ($problems['warning_count'] === 0 && $problems['error_count'] === 0))) {
                return CarbonImmutable::instance($date);
            }
        }

        return null;
    }

    /** Exact peso math in centavos (no floating-point drift). */
    private function toCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /** quantity x unit price - discount, rounded to the centavo. */
    private function lineTotalCents(string $quantity, string $unitPrice, string $discount): int
    {
        $gross = intdiv(InventoryService::toUnits($quantity) * $this->toCents($unitPrice) + 500, 1000);

        return $gross - $this->toCents($discount);
    }

    /**
     * Products are matched by POS product code first, then by name (ignoring case and extra spaces).
     * Inactive products still match: old sales can belong to products no longer sold.
     *
     * @return array{0: array<string, int>, 1: array<string, int|null>}  name => NULL means two products share that name
     */
    private function productLookups(): array
    {
        $byCode = [];
        $byName = [];

        foreach (Product::get(['id', 'pos_product_code', 'pos_product_name']) as $product) {
            if ($product->pos_product_code !== null) {
                $byCode[Str::upper($product->pos_product_code)] = $product->id;
            }

            $name = self::normalizeName($product->pos_product_name);
            $byName[$name] = array_key_exists($name, $byName) ? null : $product->id;
        }

        return [$byCode, $byName];
    }

    private function matchProduct(?string $code, string $name, array $byCode, array $byName): ?int
    {
        if ($code !== null && isset($byCode[Str::upper($code)])) {
            return $byCode[Str::upper($code)];
        }

        return $byName[self::normalizeName($name)] ?? null;
    }

    public static function normalizeName(string $name): string
    {
        return Str::lower(Str::squish($name));
    }

    private function overlappingBatch(Location $location, string $start, string $end): ?SalesImportBatch
    {
        return SalesImportBatch::where('location_id', $location->id)
            ->where('status', 'completed')
            ->whereDate('period_start', '<=', $end)
            ->whereDate('period_end', '>=', $start)
            ->orderBy('period_start')
            ->first();
    }
}
