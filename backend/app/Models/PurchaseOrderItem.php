<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'supplier_id',
        'ordered_qty',
        'received_qty',
        'unit_price',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'suggested_qty' => 'decimal:3',
            'ordered_qty' => 'decimal:3',
            'received_qty' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'total_price' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
