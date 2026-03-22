<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->string('name', 100);
            $table->string('code', 50)->nullable();
            $table->boolean('is_cogs')->default(false)->comment('是否属于销售成本');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'parent_id']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->string('expense_no', 50)->unique();
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->text('description')->nullable();
            $table->tinyInteger('input_method')->default(1)->comment('1:手动录入 2:AI录入 3:系统自动');
            $table->foreignId('ai_session_message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->json('attachment_urls')->nullable()->comment('凭证附件URL数组');
            $table->string('vendor_name', 200)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('payment_method')->default(1)->comment('1:现金 2:转账 3:微信支付 4:支付宝 5:企业网银');
            $table->tinyInteger('payment_status')->default(1)->comment('1:待支付 2:已支付 3:已报销');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'expense_date']);
            $table->index(['category_id', 'expense_date']);
            $table->index(['payment_status']);
            $table->index(['supplier_id']);
        });

        Schema::create('supplier_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('settlement_no', 50)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_purchase_amount', 12, 2)->default(0);
            $table->decimal('total_return_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('settlement_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('outstanding_amount', 12, 2)->default(0);
            $table->tinyInteger('status')->default(1)->comment('1:待对账 2:已对账 3:部分付款 4:已结清 5:有争议');
            $table->text('notes')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
            $table->index(['period_start', 'period_end']);
        });

        Schema::create('supplier_settlement_orders', function (Blueprint $table) {
            $table->foreignId('settlement_id')->constrained('supplier_settlements')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->decimal('order_amount', 12, 2);

            $table->primary(['settlement_id', 'purchase_order_id']);
        });

        Schema::create('financial_monthly_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->year('year');
            $table->tinyInteger('month');
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('total_cogs', 12, 2)->default(0);
            $table->decimal('gross_profit', 12, 2)->default(0);
            $table->decimal('gross_profit_rate', 6, 4)->nullable();
            $table->decimal('total_operating_expense', 12, 2)->default(0);
            $table->decimal('net_profit', 12, 2)->default(0);
            $table->decimal('total_waste_amount', 12, 2)->default(0);
            $table->decimal('avg_inventory_value', 12, 2)->default(0);
            $table->decimal('inventory_turnover', 8, 4)->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();

            $table->unique(['store_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_monthly_summary');
        Schema::dropIfExists('supplier_settlement_orders');
        Schema::dropIfExists('supplier_settlements');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
