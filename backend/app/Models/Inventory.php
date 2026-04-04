<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    public $timestamps = false;

    protected $table = 'inventory';

    protected $fillable = [
        'store_id',
        'product_id',
        'current_qty',
        'available_qty',
        'locked_qty',
        'avg_cost',
        'last_in_at',
        'last_out_at',
        'last_counted_at',
        'last_sold_at',
    ];

    protected function casts(): array
    {
        return [
            'current_qty' => 'decimal:3',
            'available_qty' => 'decimal:3',
            'locked_qty' => 'decimal:3',
            'avg_cost' => 'decimal:4',
            'last_in_at' => 'datetime',
            'last_out_at' => 'datetime',
            'last_counted_at' => 'datetime',
            'last_sold_at' => 'datetime',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'product_id', 'product_id')
            ->where('store_id', $this->store_id);
    }
}
