<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sales row imported from the EXTERNAL POS system. Raw source data; not created by this system.
 */
class ImportedPosSale extends Model
{
    protected $fillable = [
        'sales_import_batch_id',
        'product_id',
        'pos_product_code',
        'pos_product_name',
        'pos_receipt_no',
        'sale_datetime',
        'quantity_sold',
        'unit_price',
        'discount_amount',
        'net_amount',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'sale_datetime' => 'datetime',
            'quantity_sold' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'raw_data' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SalesImportBatch::class, 'sales_import_batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
