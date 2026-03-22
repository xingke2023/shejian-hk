<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialMonthlySummary extends Model
{
    protected $table = 'financial_monthly_summary';

    protected $fillable = [
        'store_id', 'year', 'month', 'total_revenue', 'total_cogs',
        'gross_profit', 'gross_profit_rate', 'total_operating_expense',
        'net_profit', 'total_waste_amount', 'avg_inventory_value', 'inventory_turnover',
    ];

    protected function casts(): array
    {
        return [
            'total_revenue'           => 'decimal:2',
            'total_cogs'              => 'decimal:2',
            'gross_profit'            => 'decimal:2',
            'gross_profit_rate'       => 'decimal:4',
            'total_operating_expense' => 'decimal:2',
            'net_profit'              => 'decimal:2',
            'total_waste_amount'      => 'decimal:2',
            'avg_inventory_value'     => 'decimal:2',
            'inventory_turnover'      => 'decimal:4',
            'generated_at'            => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
