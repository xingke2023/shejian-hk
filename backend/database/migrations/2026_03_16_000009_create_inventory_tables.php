<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('current_qty', 10, 3)->default(0)->comment('当前库存量');
            $table->decimal('available_qty', 10, 3)->default(0)->comment('可用库存');
            $table->decimal('locked_qty', 10, 3)->default(0)->comment('锁定量（已下单未入库）');
            $table->decimal('avg_cost', 12, 4)->default(0)->comment('移动加权平均成本');
            $table->timestamp('last_in_at')->nullable();
            $table->timestamp('last_out_at')->nullable();
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['store_id', 'product_id']);
            $table->index(['store_id', 'current_qty']);
        });

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('transaction_type')->comment('1:采购入库 2:销售出库 3:损耗 4:盘点调整 5:促销出库 6:调拨入 7:调拨出 8:退货入库');
            $table->decimal('qty_change', 10, 3)->comment('变动量（正入负出）');
            $table->decimal('qty_before', 10, 3);
            $table->decimal('qty_after', 10, 3);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            $table->string('reference_type', 50)->nullable()->comment('关联单据类型');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('关联单据ID');
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable()->comment('批次到期日');
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['store_id', 'product_id', 'created_at'], 'inv_tx_store_product_date');
            $table->index(['store_id', 'transaction_type', 'created_at'], 'inv_tx_store_type_date');
            $table->index(['reference_type', 'reference_id'], 'inv_tx_reference');
            $table->index(['expiry_date']);
        });

        Schema::create('inventory_count_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('sheet_no', 50)->unique();
            $table->tinyInteger('count_type')->default(1)->comment('1:全盘 2:部分盘 3:日常抽盘');
            $table->tinyInteger('status')->default(1)->comment('1:待盘点 2:盘点中 3:待审核 4:已完成 5:已取消');
            $table->timestamp('planned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->decimal('total_variance_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'status']);
        });

        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('count_sheet_id')->constrained('inventory_count_sheets')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_qty', 10, 3)->default(0)->comment('系统账面数量');
            $table->decimal('counted_qty', 10, 3)->nullable()->comment('实盘数量');
            $table->decimal('variance_qty', 10, 3)->default(0)->comment('差异量');
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->decimal('variance_amount', 12, 2)->default(0);
            $table->tinyInteger('variance_reason')->nullable()->comment('1:损耗 2:盗损 3:录入错误 4:其他');
            $table->string('notes', 500)->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['count_sheet_id']);
            $table->index(['product_id', 'count_sheet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_items');
        Schema::dropIfExists('inventory_count_sheets');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory');
    }
};
