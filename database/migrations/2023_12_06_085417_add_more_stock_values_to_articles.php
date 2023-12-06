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
        Schema::table('articles', function (Blueprint $table) {
            $table->integer('stock_warehouse')->default(0)->after('stock');
            $table->integer('stock_on_hand')->default(0)->after('stock_warehouse');
            $table->integer('stock_available_for_shipment')->default(0)->after('stock_on_hand');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('stock_warehouse');
            $table->dropColumn('stock_on_hand');
            $table->dropColumn('stock_available_for_shipment');
        });
    }
};
