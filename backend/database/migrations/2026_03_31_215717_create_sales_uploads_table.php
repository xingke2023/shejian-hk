<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename', 255);
            $table->string('file_path', 500);
            $table->date('sale_date');
            $table->tinyInteger('status')->default(0)->comment('0=pending 1=processing 2=completed 3=failed');
            $table->integer('total_items')->default(0);
            $table->integer('processed_items')->default(0);
            $table->integer('failed_items')->default(0);
            $table->text('error_message')->nullable();
            $table->json('raw_rows')->nullable()->comment('Excel解析出的原始行数据');
            $table->json('ai_result')->nullable()->comment('AI分析映射结果');
            $table->timestamps();

            $table->index(['store_id', 'sale_date']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_uploads');
    }
};
