<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDailySnapshot extends Model
{
    protected $fillable = [
        'store_id',
        'product_id',
        'date',
        'opening_qty',
        'received_qty',
        'sold_qty',
        'damage_qty',
        'adjustment_qty',
        'closing_qty',
        'sold_out_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'opening_qty' => 'decimal:3',
            'received_qty' => 'decimal:3',
            'sold_qty' => 'decimal:3',
            'damage_qty' => 'decimal:3',
            'adjustment_qty' => 'decimal:3',
            'closing_qty' => 'decimal:3',
            'sold_out_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 记录一笔库存变动到当日快照。
     *
     * 首次写入时固定 opening_qty（当天第一笔交易前的库存）。
     * 后续同一天的更新只累加各类型数量，并更新 closing_qty。
     * 当 closing_qty 首次降为 0 时，记录 sold_out_at（不再覆盖）。
     *
     * @param  int  $transactionType  1=进货 2=销售 3=损耗 4=盘点
     * @param  Carbon|null  $occurredAt  实际发生时间，用于记录 sold_out_at
     */
    public static function record(
        int $storeId,
        int $productId,
        float $qtyBefore,
        float $qtyChange,
        float $qtyAfter,
        int $transactionType,
        string $date,
        ?Carbon $occurredAt = null,
    ): void {
        $snapshot = self::firstOrCreate(
            ['store_id' => $storeId, 'product_id' => $productId, 'date' => $date],
            [
                'opening_qty' => $qtyBefore,
                'received_qty' => 0,
                'sold_qty' => 0,
                'damage_qty' => 0,
                'adjustment_qty' => 0,
                'closing_qty' => $qtyBefore,
                'sold_out_at' => null,
            ]
        );

        $inc = match ($transactionType) {
            1 => [
                // 进货只累加 received_qty，opening_qty 在首次创建时已冻结，不再修改
                'received_qty' => (float) $snapshot->received_qty + $qtyChange,
            ],
            2 => ['sold_qty' => (float) $snapshot->sold_qty + abs($qtyChange)],
            3 => ['damage_qty' => (float) $snapshot->damage_qty + abs($qtyChange)],
            4 => ['adjustment_qty' => (float) $snapshot->adjustment_qty + $qtyChange],
            default => [],
        };

        $update = array_merge($inc, ['closing_qty' => $qtyAfter]);

        // 首次售罄：closing_qty 降为 0 且之前未记录过
        if ($qtyAfter <= 0 && $snapshot->sold_out_at === null) {
            $update['sold_out_at'] = $occurredAt ?? now();
        }

        // 补货后重新有货：清除售罄时间
        if ($qtyAfter > 0 && $snapshot->sold_out_at !== null) {
            $update['sold_out_at'] = null;
        }

        $snapshot->update($update);
    }

    /**
     * 补录销售（无实物库存变动）：仅累加 sold_qty。
     * 用于 POST /api/inventory/sales-summary 补录场景。
     */
    public static function recordSupplement(
        int $storeId,
        int $productId,
        float $soldQty,
        string $date,
    ): void {
        $snapshot = self::where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('date', $date)
            ->first();

        if ($snapshot) {
            $snapshot->update([
                'sold_qty' => (float) $snapshot->sold_qty + $soldQty,
            ]);
        }
    }
}
