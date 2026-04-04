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
        Schema::create('daily_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->date('date');
            $table->timestamp('occurred_at');
            $table->unsignedTinyInteger('source')->comment('1=AI助手 2=手动API 3=Filament后台');
            $table->text('content')->comment('操作描述或原始指令文本');
            $table->string('intent', 32)->default('note')
                ->comment('stock_in|stock_out|sold_out|damage|adjust|supplement|note|other');
            $table->boolean('is_operational')->default(false)->comment('是否影响库存/销售数据');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('qty_change', 10, 3)->nullable()->comment('库存变动量（正入负出）');
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'date']);
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_operation_logs');
    }
};
