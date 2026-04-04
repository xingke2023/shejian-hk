<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_sellout_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('sales_order_id')->nullable()->comment('触发归零的销售单')->constrained('sales_orders')->nullOnDelete();
            $table->timestamp('sold_out_at')->comment('库存归零时间');
            $table->timestamp('restocked_at')->nullable()->comment('下次补货到货时间');
            $table->decimal('stockout_days', 8, 2)->nullable()->comment('缺货天数（补货后计算）');
            $table->timestamps();

            $table->index(['store_id', 'product_id', 'sold_out_at']);
            $table->index(['store_id', 'product_id', 'restocked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sellout_logs');
    }
};
