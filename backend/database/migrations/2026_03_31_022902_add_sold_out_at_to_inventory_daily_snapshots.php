<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_daily_snapshots', function (Blueprint $table) {
            // 当日首次售罄时间（closing_qty 首次降为 0 时写入，之后不覆盖）
            $table->timestamp('sold_out_at')->nullable()->after('closing_qty');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_daily_snapshots', function (Blueprint $table) {
            $table->dropColumn('sold_out_at');
        });
    }
};
