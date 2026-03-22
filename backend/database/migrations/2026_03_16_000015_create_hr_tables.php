<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('current_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('employee_no', 50)->nullable();
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('id_card_no', 100)->nullable()->comment('加密存储');
            $table->tinyInteger('gender')->nullable()->comment('1:男 2:女');
            $table->date('birth_date')->nullable();
            $table->tinyInteger('education')->nullable()->comment('1:初中及以下 2:高中/中专 3:大专 4:本科 5:研究生');
            $table->string('position', 100)->nullable();
            $table->tinyInteger('position_level')->default(1)->comment('1:店员 2:主管 3:店长 4:区域 5:总部');
            $table->date('hire_date')->nullable();
            $table->date('contract_expire_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->tinyInteger('status')->default(1)->comment('1:试用期 2:正式 3:离职 4:暂停');
            $table->date('resign_date')->nullable();
            $table->text('resign_reason')->nullable();
            $table->decimal('base_salary', 10, 2)->nullable();
            $table->string('emergency_contact', 100)->nullable();
            $table->string('emergency_phone', 20)->nullable();
            $table->json('skills')->nullable()->comment('技能标签');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['current_store_id', 'status']);
            $table->index(['phone']);
            $table->index(['employee_no']);
        });

        Schema::create('employee_store_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('to_store_id')->constrained('stores')->cascadeOnDelete();
            $table->date('effective_date');
            $table->string('reason', 200)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employee_id', 'effective_date']);
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('schedule_date');
            $table->time('shift_start');
            $table->time('shift_end');
            $table->tinyInteger('shift_type')->default(1)->comment('1:早班 2:中班 3:晚班 4:全天');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'employee_id', 'schedule_date']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();
            $table->timestamp('clock_in_at')->nullable();
            $table->timestamp('clock_out_at')->nullable();
            $table->tinyInteger('clock_in_source')->nullable()->comment('1:APP 2:企业微信 3:人工补录');
            $table->decimal('work_hours', 4, 2)->nullable();
            $table->decimal('overtime_hours', 4, 2)->default(0);
            $table->tinyInteger('status')->default(1)->comment('1:正常 2:迟到 3:早退 4:缺勤 5:请假');
            $table->string('exception_reason', 200)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['store_id', 'work_date']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('leave_type')->comment('1:事假 2:病假 3:年假 4:婚假 5:产假/陪产假 6:其他');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1);
            $table->text('reason')->nullable();
            $table->tinyInteger('status')->default(1)->comment('1:待审批 2:已批准 3:已拒绝 4:已撤销');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index(['store_id', 'start_date']);
        });

        Schema::create('salary_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->year('year');
            $table->tinyInteger('month');
            $table->decimal('work_days', 4, 1)->default(0);
            $table->decimal('actual_work_days', 4, 1)->default(0);
            $table->decimal('base_salary', 10, 2)->default(0);
            $table->decimal('overtime_pay', 10, 2)->default(0);
            $table->decimal('performance_bonus', 10, 2)->default(0);
            $table->decimal('sales_commission', 10, 2)->default(0);
            $table->decimal('deductions', 10, 2)->default(0);
            $table->decimal('social_insurance', 10, 2)->default(0);
            $table->decimal('income_tax', 10, 2)->default(0);
            $table->decimal('gross_salary', 10, 2)->default(0);
            $table->decimal('net_salary', 10, 2)->default(0);
            $table->tinyInteger('payment_status')->default(1)->comment('1:待发放 2:已发放 3:暂停');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'month']);
            $table->index(['store_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_records');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('employee_store_history');
        Schema::dropIfExists('employees');
    }
};
