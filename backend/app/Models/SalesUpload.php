<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesUpload extends Model
{
    protected $fillable = [
        'store_id',
        'uploaded_by',
        'original_filename',
        'file_path',
        'sale_date',
        'status',
        'total_items',
        'processed_items',
        'failed_items',
        'error_message',
        'raw_rows',
        'ai_result',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'status' => 'integer',
            'total_items' => 'integer',
            'processed_items' => 'integer',
            'failed_items' => 'integer',
            'raw_rows' => 'array',
            'ai_result' => 'array',
        ];
    }

    /** Status constants */
    const STATUS_PENDING = 0;

    const STATUS_PROCESSING = 1;

    const STATUS_COMPLETED = 2;

    const STATUS_FAILED = 3;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => '待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_FAILED => '失败',
            default => '未知',
        };
    }
}
