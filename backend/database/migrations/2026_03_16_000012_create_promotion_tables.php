<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->tinyInteger('trigger_type')->comment('1:库存阈值 2:临期天数 3:滞销天数 4:手动触发 5:节假日');
            $table->json('trigger_condition')->nullable()->comment('触发条件参数');
            $table->tinyInteger('promotion_type')->comment('1:折扣 2:满减 3:买赠 4:捆绑销售 5:限时特价');
            $table->tinyInteger('pricing_strategy')->default(1)->comment('1:固定折扣 2:AI动态定价 3:清零定价');
            $table->decimal('max_discount_rate', 5, 4)->nullable()->comment('最大折扣率下限');
            $table->tinyInteger('apply_to')->default(1)->comment('1:全品类 2:指定分类 3:指定商品');
            $table->json('apply_target_ids')->nullable();
            $table->boolean('auto_execute')->default(false)->comment('是否自动执行');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('promotion_rules')->nullOnDelete();
            $table->string('name', 200);
            $table->tinyInteger('trigger_source')->default(1)->comment('1:AI自动触发 2:店长手动 3:总部下发');
            $table->tinyInteger('status')->default(1)->comment('1:待审核 2:进行中 3:已暂停 4:已结束 5:已取消');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('ai_analysis')->nullable()->comment('AI触发时的分析快照');
            $table->decimal('total_sales_qty', 10, 3)->default(0);
            $table->decimal('total_sales_amount', 12, 2)->default(0);
            $table->decimal('total_saved_waste_amount', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'status']);
            $table->index(['store_id', 'started_at', 'ended_at']);
        });

        Schema::create('promotion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('original_price', 12, 2);
            $table->decimal('promotion_price', 12, 2);
            $table->decimal('discount_rate', 5, 4)->nullable();
            $table->decimal('ai_suggested_price', 12, 2)->nullable();
            $table->decimal('cost_price', 12, 2)->nullable()->comment('成本价（防止亏本）');
            $table->decimal('stock_qty_at_start', 10, 3)->nullable();
            $table->decimal('target_clear_qty', 10, 3)->nullable();
            $table->decimal('actual_sold_qty', 10, 3)->default(0);
            $table->timestamps();

            $table->index(['promotion_id']);
        });

        Schema::create('promotion_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('gross_profit', 12, 2)->default(0);
            $table->decimal('gross_profit_rate', 6, 4)->nullable();
            $table->decimal('clear_rate', 5, 4)->nullable()->comment('清货率');
            $table->decimal('waste_amount_prevented', 12, 2)->default(0);
            $table->decimal('customer_traffic_change', 6, 4)->nullable()->comment('客流变化率');
            $table->decimal('ai_effectiveness_score', 5, 2)->nullable();
            $table->text('lessons_learned')->nullable();
            $table->json('recommendations')->nullable();
            $table->timestamp('generated_at')->useCurrent();

            $table->index(['store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_reviews');
        Schema::dropIfExists('promotion_items');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('promotion_rules');
    }
};
