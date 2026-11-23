<?php

namespace App\Services;

use App\Http\Requests\InventoryItemRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Imports the inventory item list from a CSV file (template: docs/samples/README.md).
 *
 * Team decisions (Module 4 import):
 *  - I1: ALL-OR-NOTHING. If any row has an error, nothing is imported.
 *  - I2: An item code that already exists in the system is rejected (not updated).
 *  - I3: Categories must already exist (no automatic creation).
 */
class InventoryItemImporter
{
    public const HEADERS = ['item_code', 'name', 'category', 'item_type', 'unit_of_measure', 'unit_cost', 'reorder_level'];

    public const MAX_ROWS = 5000;

    /**
     * @return array{imported: int, errors: array<int, array{row: int, item_code: string, messages: array<int, string>}>}
     */
    public function import(string $path): array
    {
        $rows = $this->readCsv($path);

        if (isset($rows['error'])) {
            return ['imported' => 0, 'errors' => [['row' => 1, 'item_code' => '', 'messages' => [$rows['error']]]]];
        }

        // Lookups done once (not per row) for speed with ~1,000+ items.
        $categories = InventoryCategory::pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [Str::lower(trim($name)) => $id]);
        $existingCodes = InventoryItem::pluck('item_code')->flip();

        $errors = [];
        $validRows = [];
        $codesInFile = [];

        foreach ($rows as $rowNumber => $row) {
            $messages = [];

            $validator = Validator::make($row, InventoryItemRequest::fieldRules(), InventoryItemRequest::fieldMessages());
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
            }

            $code = $row['item_code'];
            if ($code !== '' && isset($codesInFile[$code])) {
                $messages[] = "Duplicate item code in this file (also on row {$codesInFile[$code]}).";
            } elseif ($code !== '' && isset($existingCodes[$code])) {
                $messages[] = 'This item code already exists in the system. Edit the existing item instead.';
            }
            if ($code !== '') {
                $codesInFile[$code] ??= $rowNumber;
            }

            $categoryId = $categories->get(Str::lower($row['category']));
            if ($row['category'] === '') {
                $messages[] = 'The category is required.';
            } elseif (! $categoryId) {
                $messages[] = "Unknown category \"{$row['category']}\". Create it first under Inventory > Categories.";
            }

            if ($messages) {
                $errors[] = ['row' => $rowNumber, 'item_code' => $code, 'messages' => $messages];

                continue;
            }

            $validRows[] = [
                'item_code' => $code,
                'name' => $row['name'],
                'inventory_category_id' => $categoryId,
                'item_type' => $row['item_type'],
                'unit_of_measure' => $row['unit_of_measure'],
                'unit_cost' => $row['unit_cost'],
                'reorder_level' => $row['reorder_level'],
                'status' => 'active',
            ];
        }

        if ($errors) {
            return ['imported' => 0, 'errors' => $errors];
        }

        DB::transaction(function () use ($validRows) {
            foreach ($validRows as $data) {
                InventoryItem::create($data);
            }
        });

        return ['imported' => count($validRows), 'errors' => []];
    }

    /**
     * Read and normalize the CSV. Returns [rowNumber => row] or ['error' => message].
     * Row numbers match what users see in Excel (header = row 1).
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return ['error' => 'The file could not be read.'];
        }

        try {
            $header = fgetcsv($handle);
            if (! $header) {
                return ['error' => 'The file is empty.'];
            }

            // Remove the invisible BOM that Excel adds to "CSV UTF-8" files.
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $header = array_map(fn ($h) => Str::lower(trim((string) $h)), $header);

            if ($header !== self::HEADERS) {
                return ['error' => 'The column headers do not match the template. Expected: '.implode(', ', self::HEADERS).'.'];
            }

            $rows = [];
            $rowNumber = 1;

            while (($values = fgetcsv($handle)) !== false) {
                $rowNumber++;

                // Skip completely blank lines.
                if (count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                if (count($rows) >= self::MAX_ROWS) {
                    return ['error' => 'The file has more than '.self::MAX_ROWS.' rows. Split it into smaller files.'];
                }

                $values = array_pad(array_slice($values, 0, count(self::HEADERS)), count(self::HEADERS), '');
                $row = array_combine(self::HEADERS, array_map(fn ($v) => trim((string) $v), $values));

                // Same normalization as the Add Item form.
                $row['item_code'] = Str::upper($row['item_code']);
                $row['item_type'] = Str::lower($row['item_type']);

                $rows[$rowNumber] = $row;
            }

            if (! $rows) {
                return ['error' => 'The file has no item rows below the header.'];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
