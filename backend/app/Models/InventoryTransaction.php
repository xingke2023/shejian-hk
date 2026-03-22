<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'product_id',
        'transaction_type',
        'qty_change',
        'qty_before',
        'qty_after',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'batch_no',
        'expiry_date',
        'operator_id',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_change'  => 'decimal:3',
            'qty_before'  => 'decimal:3',
            'qty_after'   => 'decimal:3',
            'unit_cost'   => 'decimal:4',
            'total_cost'  => 'decimal:2',
            'expiry_date' => 'date',
            'created_at'  => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
