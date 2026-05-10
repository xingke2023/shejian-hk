<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierRefundClaim extends Model
{
    protected $fillable = [
        'store_id',
        'supplier_id',
        'claim_no',
        'status',
        'total_items',
        'total_qty',
        'total_amount',
        'submitted_at',
        'resolved_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_qty' => 'decimal:3',
            'total_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            1 => '草稿',
            2 => '已提交',
            3 => '供应商确认',
            4 => '已退款',
            5 => '已拒绝',
            default => '未知',
        };
    }

    public static function generateClaimNo(): string
    {
        $date = now()->format('Ymd');
        $prefix = "RC-{$date}-";

        $last = static::where('claim_no', 'like', $prefix.'%')
            ->orderByDesc('claim_no')
            ->value('claim_no');

        $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
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
        return $this->hasMany(SupplierRefundClaimItem::class, 'claim_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
