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
        Schema::create('inventory_daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->date('date');
            $table->decimal('opening_qty', 10, 3)->default(0)->comment('往日库存（当天第一笔交易前）');
            $table->decimal('received_qty', 10, 3)->default(0)->comment('今日进货合计');
            $table->decimal('sold_qty', 10, 3)->default(0)->comment('今日销售出库合计');
            $table->decimal('damage_qty', 10, 3)->default(0)->comment('今日损耗合计');
            $table->decimal('adjustment_qty', 10, 3)->default(0)->comment('今日盘点调整');
            $table->decimal('closing_qty', 10, 3)->default(0)->comment('今日结算库存（实时更新）');
            $table->timestamps();

            $table->unique(['store_id', 'product_id', 'date']);
            $table->index(['store_id', 'date']);
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_daily_snapshots');
    }
};
