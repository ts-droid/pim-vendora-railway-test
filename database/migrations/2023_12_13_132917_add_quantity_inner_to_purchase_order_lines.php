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
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->integer('suggested_quantity_inner')->default(0)->after('suggested_quantity_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn('suggested_quantity_month');
        });
    }
};
