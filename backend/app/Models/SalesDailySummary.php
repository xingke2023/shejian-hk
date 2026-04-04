<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesDailySummary extends Model
{
    protected $fillable = [
        'store_id',
        'product_id',
        'sale_date',
        'sales_qty',
        'sales_amount',
        'transaction_count',
        'avg_selling_price',
        'pos_qty',
        'pos_amount',
        'supplement_qty',
        'supplement_amount',
        'ai_qty',
        'ai_amount',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'sales_qty' => 'decimal:3',
            'sales_amount' => 'decimal:2',
            'avg_selling_price' => 'decimal:4',
            'pos_qty' => 'decimal:3',
            'pos_amount' => 'decimal:2',
            'supplement_qty' => 'decimal:3',
            'supplement_amount' => 'decimal:2',
            'ai_qty' => 'decimal:3',
            'ai_amount' => 'decimal:2',
        ];
    }

    /**
     * 累加每日销售汇总（按来源分类）。
     *
     * @param  'pos'|'supplement'|'ai'  $source
     */
    public static function accumulate(
        int $storeId,
        int $productId,
        string $date,
        float $qty,
        float $amount,
        string $source = 'pos',
    ): self {
        $summary = self::firstOrCreate(
            ['store_id' => $storeId, 'product_id' => $productId, 'sale_date' => $date],
            ['sales_qty' => 0, 'sales_amount' => 0, 'transaction_count' => 0,
                'pos_qty' => 0, 'pos_amount' => 0,
                'supplement_qty' => 0, 'supplement_amount' => 0,
                'ai_qty' => 0, 'ai_amount' => 0],
        );

        $newQty = (float) $summary->sales_qty + $qty;
        $newAmount = (float) $summary->sales_amount + $amount;

        $summary->update([
            'sales_qty' => $newQty,
            'sales_amount' => $newAmount,
            'transaction_count' => $summary->transaction_count + 1,
            'avg_selling_price' => $newQty > 0 ? round($newAmount / $newQty, 4) : null,
            "{$source}_qty" => (float) $summary->{"{$source}_qty"} + $qty,
            "{$source}_amount" => (float) $summary->{"{$source}_amount"} + $amount,
        ]);

        return $summary->fresh();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
