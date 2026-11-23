<?php

namespace App\Services;

use App\Http\Requests\InventoryItemRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Support\CsvReader;
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
     */
    private function readCsv(string $path): array
    {
        $csv = CsvReader::read($path, self::HEADERS, self::MAX_ROWS);
        if (isset($csv['error'])) {
            return $csv;
        }

        // Same normalization as the Add Item form.
        return array_map(function (array $row) {
            $row['item_code'] = Str::upper($row['item_code']);
            $row['item_type'] = Str::lower($row['item_type']);

            return $row;
        }, $csv['rows']);
    }
}
