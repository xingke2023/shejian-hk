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
        Schema::create('sales_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('product_id')->constrained('products');
            $table->date('sale_date');
            $table->decimal('sales_qty', 10, 3)->default(0);
            $table->decimal('sales_amount', 12, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->decimal('avg_selling_price', 10, 4)->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'product_id', 'sale_date']);
            $table->index(['store_id', 'sale_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_daily_summaries');
    }
};
