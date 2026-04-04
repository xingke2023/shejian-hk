<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Supplier finance tables — no models, no controllers, not part of MVP scope
        Schema::dropIfExists('supplier_price_history');
        Schema::dropIfExists('supplier_settlement_orders');
        Schema::dropIfExists('supplier_settlements');

        // Finance monthly summary — model exists but is never queried or written to
        Schema::dropIfExists('financial_monthly_summary');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
