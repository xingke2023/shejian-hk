<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('purchase_order_item_id')->nullable()->index();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->decimal('qty', 10, 3);
            $table->decimal('unit_cost', 10, 4)->nullable();
            $table->decimal('total_claimed', 10, 2)->nullable();
            $table->string('reason', 100);
            $table->json('image_paths')->nullable();
            // 1=待提交 2=已提交供应商 3=已退款 4=已关闭
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamp('occurred_at')->useCurrent();
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('store_id')->references('id')->on('stores');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('operator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_records');
    }
};
