<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_daily_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('sale_date');
            $table->decimal('sales_qty', 10, 3)->default(0);
            $table->decimal('sales_amount', 12, 2)->default(0);
            $table->decimal('sales_cost', 12, 2)->default(0);
            $table->decimal('gross_profit', 12, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->decimal('avg_selling_price', 12, 4)->nullable();
            $table->decimal('waste_qty', 10, 3)->default(0);
            $table->boolean('is_promotion_day')->default(false);
            $table->tinyInteger('weather_condition')->nullable()->comment('1:晴 2:阴 3:雨 4:雪 5:极端天气');
            $table->boolean('is_holiday')->default(false);
            $table->string('holiday_type', 50)->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'product_id', 'sale_date']);
            $table->index(['store_id', 'sale_date']);
            $table->index(['product_id', 'sale_date']);
            $table->index(['sale_date', 'is_holiday']);
        });

        Schema::create('ai_forecast_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('model_type', 50)->comment('sarima, lstm, xgboost, hybrid');
            $table->string('version', 20)->default('1.0.0');
            $table->json('target_category_ids')->nullable()->comment('适用商品分类ID列表');
            $table->json('hyperparameters')->nullable()->comment('模型超参数');
            $table->json('feature_config')->nullable()->comment('特征配置');
            $table->integer('training_period_days')->default(90)->comment('训练使用的历史天数');
            $table->integer('forecast_horizon_days')->default(7)->comment('预测未来天数');
            $table->decimal('accuracy_mape', 6, 4)->nullable()->comment('平均绝对百分比误差');
            $table->decimal('accuracy_rmse', 10, 4)->nullable();
            $table->timestamp('last_trained_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_forecast_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('model_id')->constrained('ai_forecast_models')->cascadeOnDelete();
            $table->date('forecast_date')->comment('预测目标日期');
            $table->timestamp('generated_at')->useCurrent();
            $table->decimal('predicted_qty', 10, 3);
            $table->decimal('predicted_qty_low', 10, 3)->nullable()->comment('预测下界（80%置信区间）');
            $table->decimal('predicted_qty_high', 10, 3)->nullable();
            $table->decimal('actual_qty', 10, 3)->nullable()->comment('实际销量（事后回填）');
            $table->decimal('forecast_error', 10, 4)->nullable();
            $table->json('input_features')->nullable()->comment('预测使用的特征值快照');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['store_id', 'product_id', 'forecast_date']);
            $table->index(['model_id', 'generated_at']);
            $table->index(['forecast_date']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('order_no', 50)->unique();
            $table->tinyInteger('order_type')->default(1)->comment('1:AI建议单 2:手动创建 3:紧急补货');
            $table->tinyInteger('status')->default(1)->comment('1:草稿 2:待审核 3:已确认 4:配送中 5:已收货 6:已取消');
            $table->unsignedBigInteger('forecast_session_id')->nullable()->comment('关联预测会话');
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'status']);
            $table->index(['supplier_id', 'status']);
            $table->index(['expected_delivery_date']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('forecast_result_id')->nullable()->constrained('ai_forecast_results')->nullOnDelete();
            $table->decimal('suggested_qty', 10, 3)->nullable()->comment('AI建议采购量');
            $table->decimal('ordered_qty', 10, 3);
            $table->decimal('received_qty', 10, 3)->default(0);
            $table->decimal('unit_price', 12, 4);
            $table->decimal('total_price', 12, 2);
            $table->text('ai_suggestion_reason')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id']);
            $table->index(['product_id']);
        });

        Schema::create('ai_order_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('review_period_start');
            $table->date('review_period_end');
            $table->foreignId('model_id')->constrained('ai_forecast_models')->cascadeOnDelete();
            $table->integer('total_products_reviewed')->default(0);
            $table->decimal('avg_forecast_accuracy', 6, 4)->nullable();
            $table->json('overstock_products')->nullable()->comment('过量采购商品列表');
            $table->json('understock_products')->nullable()->comment('短缺商品列表');
            $table->decimal('waste_amount', 12, 2)->default(0);
            $table->json('adjustment_suggestions')->nullable()->comment('模型调整建议参数');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_auto_generated')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['store_id', 'review_period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_order_reviews');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('ai_forecast_results');
        Schema::dropIfExists('ai_forecast_models');
        Schema::dropIfExists('sales_daily_summary');
    }
};
