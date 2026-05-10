<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierRefundClaimItem extends Model
{
    protected $fillable = [
        'claim_id',
        'damage_record_id',
        'product_id',
        'product_name',
        'qty',
        'unit_cost',
        'claimed_amount',
        'purchase_order_id',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'claimed_amount' => 'decimal:2',
        ];
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(SupplierRefundClaim::class, 'claim_id');
    }

    public function damageRecord(): BelongsTo
    {
        return $this->belongsTo(DamageRecord::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
