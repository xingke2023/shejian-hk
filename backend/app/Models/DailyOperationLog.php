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
        'is_failed',
        'http_status_code',
        'error_message',
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
            'is_failed' => 'boolean',
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
     * @param  string  $intent  stock_in|stock_out|sold_out|damage|adjust|supplement|note|other|error
     * @param  int  $source  1=AI 2=手动API 3=Filament后台
     * @param  bool  $isOperational  是否影响库存/销售
     * @param  bool  $isFailed  是否为失败调用
     * @param  int|null  $httpStatusCode  HTTP状态码（失败时记录）
     * @param  string|null  $errorMessage  错误描述
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
        ?Carbon $occurredAt = null,
        bool $isFailed = false,
        ?int $httpStatusCode = null,
        ?string $errorMessage = null,
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
            'is_failed' => $isFailed,
            'http_status_code' => $httpStatusCode,
            'error_message' => $errorMessage,
            'product_id' => $productId,
            'qty_change' => $qtyChange,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'operator_id' => $operatorId,
        ]);
    }
}
