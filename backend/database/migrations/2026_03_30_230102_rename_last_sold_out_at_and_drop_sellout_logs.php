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
        Schema::table('inventory', function (Blueprint $table) {
            $table->renameColumn('last_sold_out_at', 'last_sold_at');
        });

        Schema::dropIfExists('product_sellout_logs');
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->renameColumn('last_sold_at', 'last_sold_out_at');
        });
    }
};
