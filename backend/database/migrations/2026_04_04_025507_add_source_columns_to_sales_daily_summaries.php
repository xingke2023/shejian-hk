<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_daily_summaries', function (Blueprint $table) {
            // 收银台逐笔
            $table->decimal('pos_qty', 10, 3)->default(0)->after('transaction_count');
            $table->decimal('pos_amount', 12, 2)->default(0)->after('pos_qty');
            // 人工/API 补录
            $table->decimal('supplement_qty', 10, 3)->default(0)->after('pos_amount');
            $table->decimal('supplement_amount', 12, 2)->default(0)->after('supplement_qty');
            // AI 助手录入
            $table->decimal('ai_qty', 10, 3)->default(0)->after('supplement_amount');
            $table->decimal('ai_amount', 12, 2)->default(0)->after('ai_qty');
        });
    }

    public function down(): void
    {
        Schema::table('sales_daily_summaries', function (Blueprint $table) {
            $table->dropColumn(['pos_qty', 'pos_amount', 'supplement_qty', 'supplement_amount', 'ai_qty', 'ai_amount']);
        });
    }
};
