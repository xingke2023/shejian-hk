<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('brand', 100)->nullable();
            $table->string('address', 300)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('distance_to_store', 8, 2)->nullable()->comment('距最近自家门店（米）');
            $table->foreignId('nearest_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->json('channels')->nullable()->comment('情报采集渠道');
            $table->tinyInteger('status')->default(1)->comment('0:停止监控 1:正常监控');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('competitor_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('competitor_product_name', 200);
            $table->string('competitor_product_code', 100)->nullable();
            $table->string('spec', 200)->nullable();
            $table->decimal('match_confidence', 5, 4)->nullable()->comment('与自家商品匹配置信度');
            $table->boolean('is_manually_confirmed')->default(false);
            $table->timestamps();

            $table->index(['competitor_id']);
            $table->index(['product_id']);
        });

        Schema::create('competitor_price_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete()->comment('对应自家商品（冗余）');
            $table->decimal('price', 12, 2);
            $table->decimal('original_price', 12, 2)->nullable()->comment('划线价');
            $table->boolean('is_promotion')->default(false);
            $table->tinyInteger('collect_source')->default(1)->comment('1:人工录入 2:APP扫码 3:爬虫 4:第三方API');
            $table->string('collect_channel', 100)->nullable();
            $table->string('image_url', 500)->nullable()->comment('采集凭证图片');
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['competitor_id', 'product_id', 'collected_at'], 'cpr_competitor_product_date');
            $table->index(['product_id', 'collected_at'], 'cpr_product_date');
            $table->index(['collected_at']);
        });

        Schema::create('competitor_hot_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_product_id')->constrained()->cascadeOnDelete();
            $table->date('identified_date');
            $table->decimal('heat_score', 5, 2)->nullable();
            $table->json('evidence')->nullable()->comment('热度证据');
            $table->foreignId('our_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->tinyInteger('recommendation')->nullable()->comment('1:引进建议 2:加量建议 3:已有无需操作');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['competitor_id', 'identified_date']);
        });

        Schema::create('intelligence_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->date('report_period_start');
            $table->date('report_period_end');
            $table->tinyInteger('report_type')->default(1)->comment('1:周报 2:月报 3:专项分析');
            $table->json('price_gap_summary')->nullable();
            $table->json('hot_products_summary')->nullable();
            $table->text('ai_insights')->nullable();
            $table->json('action_recommendations')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->boolean('is_auto_generated')->default(true);

            $table->index(['organization_id', 'report_type', 'report_period_start'], 'ir_org_type_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_reports');
        Schema::dropIfExists('competitor_hot_products');
        Schema::dropIfExists('competitor_price_records');
        Schema::dropIfExists('competitor_products');
        Schema::dropIfExists('competitors');
    }
};
