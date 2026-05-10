<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_refund_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedBigInteger('supplier_id')->index();
            $table->string('claim_no', 30)->unique();
            // 1=草稿 2=已提交 3=供应商确认 4=已退款 5=已拒绝
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedInteger('total_items')->default(0);
            $table->decimal('total_qty', 10, 3)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores');
            $table->foreign('supplier_id')->references('id')->on('suppliers');
        });

        Schema::create('supplier_refund_claim_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('claim_id')->index();
            $table->unsignedBigInteger('damage_record_id')->index();
            $table->unsignedBigInteger('product_id');
            $table->string('product_name', 100);
            $table->decimal('qty', 10, 3);
            $table->decimal('unit_cost', 10, 4)->nullable();
            $table->decimal('claimed_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->timestamps();

            $table->foreign('claim_id')->references('id')->on('supplier_refund_claims')->cascadeOnDelete();
            $table->foreign('damage_record_id')->references('id')->on('damage_records');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_refund_claim_items');
        Schema::dropIfExists('supplier_refund_claims');
    }
};
