<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name', 200);
            $table->string('code', 100)->nullable()->comment('SKU编码');
            $table->string('barcode', 100)->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('unit', 20)->comment('基本单位：斤/个/箱');
            $table->string('spec', 200)->nullable()->comment('规格描述：500g/袋');
            $table->json('image_urls')->nullable();
            $table->text('description')->nullable();
            $table->integer('shelf_life_days')->nullable()->comment('保质期天数');
            $table->tinyInteger('storage_condition')->default(1)->comment('1:常温 2:冷藏 3:冷冻');
            $table->boolean('is_fresh')->default(false)->comment('是否生鲜品');
            $table->decimal('min_order_qty', 10, 3)->default(1)->comment('最小采购量');
            $table->string('purchase_unit', 20)->nullable()->comment('采购单位：箱');
            $table->decimal('purchase_unit_qty', 10, 3)->nullable()->comment('采购单位含基本单位数量');
            $table->tinyInteger('status')->default(1)->comment('0:下架 1:正常 2:待审核');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['category_id']);
            $table->index(['barcode']);
            $table->index(['code']);
        });

        Schema::create('store_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('shelf_position', 100)->nullable()->comment('货架位置');
            $table->decimal('selling_price', 12, 2)->nullable()->comment('当前零售价');
            $table->decimal('min_stock_alert', 10, 3)->nullable()->comment('库存预警下限');
            $table->decimal('max_stock_limit', 10, 3)->nullable()->comment('库存上限');
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'product_id']);
            $table->index(['store_id', 'is_active']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_products');
        Schema::dropIfExists('products');
    }
};
