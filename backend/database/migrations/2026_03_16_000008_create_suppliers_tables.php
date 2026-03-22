<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('code', 50)->nullable();
            $table->string('contact_name', 100)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_wechat', 100)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('business_license', 200)->nullable();
            $table->tinyInteger('payment_terms')->default(1)->comment('1:现款 2:月结 3:季结');
            $table->integer('payment_days')->default(0)->comment('账期天数');
            $table->integer('delivery_lead_days')->default(1)->comment('平均交货周期（天）');
            $table->tinyInteger('rating')->nullable()->comment('1-5星评级');
            $table->tinyInteger('status')->default(1)->comment('0:停用 1:正常');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_product_code', 100)->nullable()->comment('供应商侧商品编码');
            $table->decimal('purchase_price', 12, 2)->comment('当前采购单价');
            $table->decimal('min_order_qty', 10, 3)->default(1);
            $table->integer('delivery_lead_days')->nullable();
            $table->boolean('is_primary')->default(false)->comment('是否为该商品首选供应商');
            $table->date('price_effective_date')->nullable();
            $table->date('price_expired_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_primary']);
            $table->index(['supplier_id']);
        });

        Schema::create('supplier_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_product_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_price', 12, 2);
            $table->decimal('new_price', 12, 2);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_reason', 200)->nullable();
            $table->date('effective_date');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['supplier_product_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_price_history');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('suppliers');
    }
};
