<?php

namespace Database\Seeders;

use App\Models\ImportedPosSale;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\SalesImportBatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * SAMPLE / TEST DATA ONLY — for trying out Sales Analysis (module 8) before real POS files exist.
 * NOT real Stella's Cuisine products, prices, costs or sales. Never run it on the live system.
 *
 *   php artisan db:seed --class=SampleSalesDataSeeder
 *
 * Creates 6 SAMPLE products (codes SMP-AN-*) and 60 days of SAMPLE sales (up to yesterday) for the
 * first two active branches, as imports named "SAMPLE generated test data". Running it again does nothing.
 * To remove it: delete those imports under POS Sales Imports (only possible before running an analysis).
 */
class SampleSalesDataSeeder extends Seeder
{
    private const DAYS = 60;

    /** [name, category, unit cost, price, base daily qty, trend per day, weekend boost] — all invented. */
    private const PRODUCTS = [
        ['SAMPLE Bilao A', 'Cooked Food', 600, 1000, 6, 0.05, 4],
        ['SAMPLE Bilao B', 'Cooked Food', 700, 900, 3, 0.00, 2],
        ['SAMPLE Kakanin Tray', 'Kakanin', 150, 300, 8, 0.03, 5],
        ['SAMPLE Kakanin Pack', 'Kakanin', 40, 60, 15, -0.05, 3],
        ['SAMPLE Drink Large', 'Beverages', 20, 60, 20, 0.10, 6],
        ['SAMPLE Drink Small', 'Beverages', 25, 35, 4, 0.00, 1],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SampleSalesDataSeeder must never run on the live (production) system.');
        }

        if (SalesImportBatch::where('file_name', 'SAMPLE generated test data')->exists()) {
            $this->command?->info('SAMPLE sales already exist. Nothing was added.');

            return;
        }

        $branches = Location::where('type', Location::TYPE_BRANCH)->where('status', 'active')->orderBy('id')->take(2)->get();
        $admin = User::where('username', 'admin')->first() ?? User::firstOrFail();
        $from = today()->subDays(self::DAYS);
        $to = today()->subDay();

        mt_srand(20261124); // fixed seed: the same SAMPLE data every time

        DB::transaction(function () use ($branches, $admin, $from, $to) {
            $products = [];
            foreach (self::PRODUCTS as $i => [$name, $category, $cost, $price]) {
                $item = InventoryItem::firstOrCreate(['item_code' => sprintf('SMP-AN-%02d', $i + 1)], [
                    'inventory_category_id' => InventoryCategory::where('name', $category)->value('id') ?? InventoryCategory::first()->id,
                    'name' => $name, 'item_type' => InventoryItem::TYPE_FINISHED_PRODUCT, 'unit_of_measure' => 'pc',
                    'unit_cost' => $cost, 'reorder_level' => 0, 'status' => 'active',
                ]);
                $products[$i] = Product::firstOrCreate(['inventory_item_id' => $item->id],
                    ['pos_product_name' => $name, 'pos_product_code' => sprintf('SMP-AN-%02d', $i + 1), 'selling_price' => $price]);
            }

            foreach ($branches as $b => $branch) {
                $batch = SalesImportBatch::create([
                    'location_id' => $branch->id, 'imported_by' => $admin->id, 'file_name' => 'SAMPLE generated test data',
                    'period_start' => $from, 'period_end' => $to, 'imported_at' => now(), 'status' => 'completed',
                ]);

                $rows = [];
                $receipt = 0;
                for ($day = $from->copy(), $t = 0; $day->lte($to); $day->addDay(), $t++) {
                    foreach (self::PRODUCTS as $i => [$name, , , $price, $base, $trend, $weekend]) {
                        $qty = $base * (1 - 0.3 * $b) + $trend * $t + ($day->isWeekend() ? $weekend : 0) + mt_rand(-20, 20) / 10;
                        $qty = max(0, (int) round($qty));
                        // Split the day's quantity into receipts of 1–3 pieces.
                        while ($qty > 0) {
                            $lineQty = min($qty, mt_rand(1, 3));
                            $qty -= $lineQty;
                            $receipt++;
                            $at = $day->copy()->setTime(mt_rand(8, 19), mt_rand(0, 59));
                            $rows[] = [
                                'sales_import_batch_id' => $batch->id, 'product_id' => $products[$i]->id,
                                'pos_product_code' => $products[$i]->pos_product_code, 'pos_product_name' => $name,
                                'pos_receipt_no' => sprintf('SMP-%d-%06d', $branch->id, $receipt),
                                'sale_datetime' => $at, 'quantity_sold' => $lineQty, 'unit_price' => $price,
                                'discount_amount' => 0, 'net_amount' => $lineQty * $price,
                                'raw_data' => json_encode(['note' => 'SAMPLE generated test data']),
                                'created_at' => now(), 'updated_at' => now(),
                            ];
                        }
                    }
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    ImportedPosSale::insert($chunk);
                }
                $batch->update(['total_records' => count($rows)]);
            }
        });

        $this->command?->info('SAMPLE products and '.self::DAYS.' days of SAMPLE sales were added for: '.$branches->pluck('name')->join(', ').'.');
    }
}
