<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyOperationLog extends Model
{
    protected $fillable = [
        'store_id',
        'date',
        'occurred_at',
        'source',
        'content',
        'intent',
        'is_operational',
        'product_id',
        'qty_change',
        'reference_type',
        'reference_id',
        'operator_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'occurred_at' => 'datetime',
            'is_operational' => 'boolean',
            'qty_change' => 'decimal:3',
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

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * 记录一条操作日志。
     *
     * @param  string  $content  人可读描述
     * @param  string  $intent  stock_in|stock_out|sold_out|damage|adjust|supplement|note|other
     * @param  int  $source  1=AI 2=手动API 3=Filament后台
     * @param  bool  $isOperational  是否影响库存/销售
     * @param  Carbon|null  $occurredAt  默认 now()
     */
    public static function write(
        int $storeId,
        string $content,
        string $intent = 'note',
        int $source = 2,
        bool $isOperational = false,
        ?int $productId = null,
        ?float $qtyChange = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $operatorId = null,
        ?Carbon $occurredAt = null
    ): self {
        $at = $occurredAt ?? now();

        return self::create([
            'store_id' => $storeId,
            'date' => $at->toDateString(),
            'occurred_at' => $at,
            'source' => $source,
            'content' => $content,
            'intent' => $intent,
            'is_operational' => $isOperational,
            'product_id' => $productId,
            'qty_change' => $qtyChange,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'operator_id' => $operatorId,
        ]);
    }
}
