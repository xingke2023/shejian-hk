<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'category_id',
        'supplier_id',
        'name',
        'code',
        'barcode',
        'brand',
        'unit',
        'spec',
        'image_urls',
        'description',
        'shelf_life_days',
        'storage_condition',
        'is_fresh',
        'min_order_qty',
        'purchase_unit',
        'purchase_unit_qty',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'image_urls'  => 'array',
            'is_fresh'    => 'boolean',
            'min_order_qty'        => 'decimal:3',
            'purchase_unit_qty'    => 'decimal:3',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /**
     * 按名称模糊匹配商品，找不到则自动创建。
     */
    public static function findOrCreateByName(string $name, int $organizationId = 1): self
    {
        $product = self::where('organization_id', $organizationId)
            ->where('name', 'like', '%'.$name.'%')
            ->first();

        if ($product) {
            return $product;
        }

        return self::create([
            'organization_id' => $organizationId,
            'name'            => $name,
            'unit'            => '斤',
            'is_fresh'        => true,
            'status'          => 1,
        ]);
    }
}
