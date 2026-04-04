<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop FK on purchase_order_items that references ai_forecast_results,
        // and remove AI-forecast-only columns that are never populated
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign('purchase_order_items_forecast_result_id_foreign');
            $table->dropColumn(['forecast_result_id', 'suggested_qty', 'ai_suggestion_reason']);
        });

        // Remove unused AI forecast reference from purchase_orders
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('forecast_session_id');
        });

        // ai_forecast_results references ai_forecast_models, drop child first
        Schema::dropIfExists('ai_order_reviews');
        Schema::dropIfExists('ai_forecast_results');
        Schema::dropIfExists('ai_forecast_models');

        // inventory_count_items references inventory_count_sheets
        Schema::dropIfExists('inventory_count_items');
        Schema::dropIfExists('inventory_count_sheets');

        // ai_command_templates — never referenced in application code
        Schema::dropIfExists('ai_command_templates');

        // sales_daily_summary (singular) — orphaned old table,
        // replaced by sales_daily_summaries (plural) used by SalesDailySummary model
        Schema::dropIfExists('sales_daily_summary');
    }

    public function down(): void
    {
        // Tables removed intentionally; no rollback provided.
    }
};
