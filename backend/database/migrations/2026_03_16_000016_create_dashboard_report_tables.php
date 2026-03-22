<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->tinyInteger('scope')->default(3)->comment('1:个人 2:门店 3:区域 4:总部');
            $table->json('widgets')->nullable()->comment('组件配置数组');
            $table->json('filters')->nullable()->comment('默认筛选条件');
            $table->integer('refresh_interval')->default(0)->comment('自动刷新间隔（秒），0不刷新');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_default']);
            $table->index(['store_id', 'scope']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type', 50)->comment('daily_review, weekly_review, monthly_review, custom');
            $table->string('title', 200);
            $table->date('period_start');
            $table->date('period_end');
            $table->longText('content')->nullable();
            $table->json('data_snapshot')->nullable()->comment('核心指标数据快照');
            $table->text('ai_analysis')->nullable();
            $table->json('charts_config')->nullable();
            $table->tinyInteger('status')->default(1)->comment('1:生成中 2:已完成 3:失败');
            $table->boolean('is_auto_generated')->default(true);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'report_type', 'period_start'], 'reports_store_type_period');
            $table->index(['organization_id', 'report_type']);
        });

        Schema::create('custom_report_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->json('data_sources')->nullable()->comment('数据源配置');
            $table->json('filters')->nullable()->comment('筛选条件配置');
            $table->json('columns')->nullable()->comment('报表列定义');
            $table->json('chart_types')->nullable();
            $table->string('schedule_cron', 50)->nullable()->comment('定时生成表达式');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_report_templates');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('dashboard_configs');
    }
};
