<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('order_no', 50)->unique()->comment('流水号');
            $table->foreignId('cashier_id')->nullable()->comment('收银员')->constrained('users')->nullOnDelete();
            $table->decimal('total_amount', 10, 2)->comment('应收金额');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('整单折扣');
            $table->decimal('paid_amount', 10, 2)->comment('实收金额');
            $table->tinyInteger('payment_method')->default(1)->comment('1:现金 2:微信 3:支付宝 4:银行卡 5:混合');
            $table->tinyInteger('status')->default(1)->comment('1:已完成 2:已退款 3:部分退款');
            $table->timestamp('sold_at')->comment('交易时间');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'sold_at']);
            $table->index(['cashier_id', 'sold_at']);
            $table->index('sold_at');
        });

        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('qty', 10, 3)->comment('数量');
            $table->decimal('unit_price', 10, 2)->comment('售价');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('行级折扣');
            $table->decimal('subtotal', 10, 2)->comment('小计');
            $table->decimal('cost_price', 10, 4)->nullable()->comment('成本价（用于毛利计算）');
            $table->timestamps();

            $table->index('sales_order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
    }
};
