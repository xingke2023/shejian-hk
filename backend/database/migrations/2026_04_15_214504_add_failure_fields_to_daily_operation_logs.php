<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_operation_logs', function (Blueprint $table) {
            $table->boolean('is_failed')->default(false)->after('is_operational');
            $table->smallInteger('http_status_code')->nullable()->after('is_failed');
            $table->string('error_message', 500)->nullable()->after('http_status_code');
        });
    }

    public function down(): void
    {
        Schema::table('daily_operation_logs', function (Blueprint $table) {
            $table->dropColumn(['is_failed', 'http_status_code', 'error_message']);
        });
    }
};
