<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'supplier_id', 'product_id', 'supplier_product_code', 'purchase_price',
        'min_order_qty', 'delivery_lead_days', 'is_primary',
        'price_effective_date', 'price_expired_date',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price'       => 'decimal:2',
            'min_order_qty'        => 'decimal:3',
            'is_primary'           => 'boolean',
            'price_effective_date' => 'date',
            'price_expired_date'   => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
