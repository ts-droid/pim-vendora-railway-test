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
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->tinyInteger('status_sent_external')->default(0);
            $table->tinyInteger('status_shipment_created')->default(0);
            $table->tinyInteger('status_shipment_picked')->default(0);
            $table->tinyInteger('status_shipment_sent')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn('status_sent_external');
            $table->dropColumn('status_shipment_created');
            $table->dropColumn('status_shipment_picked');
            $table->dropColumn('status_shipment_sent');
        });
    }
};
