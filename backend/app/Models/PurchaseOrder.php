<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_id',
        'supplier_id',
        'order_no',
        'order_type',
        'status',
        'expected_delivery_date',
        'actual_delivery_date',
        'total_amount',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_delivery_date' => 'date',
            'actual_delivery_date' => 'date',
            'total_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 状态标签 */
    public function statusLabel(): string
    {
        return match ($this->status) {
            1 => '草稿',
            2 => '待审核',
            3 => '已确认',
            4 => '配送中',
            5 => '已收货',
            6 => '已取消',
            default => '未知',
        };
    }

    /** 自动生成单号，如 PO-20260322-00001 */
    public static function generateOrderNo(int $storeId): string
    {
        $date = now()->format('Ymd');
        $prefix = "PO-{$date}-";

        $last = static::withTrashed()
            ->where('order_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('order_no')
            ->value('order_no');

        $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
