<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DamageRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_id',
        'product_id',
        'purchase_order_item_id',
        'supplier_id',
        'qty',
        'unit_cost',
        'total_claimed',
        'reason',
        'image_paths',
        'status',
        'occurred_at',
        'operator_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'total_claimed' => 'decimal:2',
            'image_paths' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            1 => '待提交',
            2 => '已提交供应商',
            3 => '已退款',
            4 => '已关闭',
            default => '未知',
        };
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
