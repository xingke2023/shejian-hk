<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->tinyInteger('gender')->default(0)->comment('0:未知 1:男 2:女');
            $table->tinyInteger('age')->unsigned()->nullable();
            $table->json('districts')->nullable()->comment('意向工作区域，如["筲箕湾","柴湾"]');
            $table->json('work_types')->nullable()->comment('工作类型，如["全职","小时工"]');
            $table->json('positions')->nullable()->comment('意向岗位，如["收银员","理货员"]');
            $table->decimal('experience_years', 3, 1)->nullable()->comment('工作经验年数');
            $table->integer('salary_min')->nullable()->comment('薪资下限');
            $table->integer('salary_max')->nullable()->comment('薪资上限');
            $table->tinyInteger('salary_unit')->default(1)->comment('1:月 2:日 3:小时');
            $table->tinyInteger('education')->nullable()->comment('1:初中 2:高中 3:大专 4:本科');
            $table->date('availability_date')->nullable()->comment('最早到岗日期');
            $table->json('languages')->nullable()->comment('语言能力，如["粤语","普通话"]');
            $table->json('skills')->nullable()->comment('技能标签，如["生鲜处理","收银"]');
            $table->text('raw_text')->nullable()->comment('原始输入文本');
            $table->tinyInteger('source')->default(1)->comment('1:手动录入 2:AI解析 3:文件上传');
            $table->tinyInteger('status')->default(1)->comment('0:无效 1:求职中 2:已入职 3:暂不求职');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resumes');
    }
};
