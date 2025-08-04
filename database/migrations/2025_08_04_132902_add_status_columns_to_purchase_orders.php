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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->tinyInteger('status_sent_to_supplier')->default(0);
            $table->tinyInteger('status_confirmed_by_supplier')->default(0);
            $table->tinyInteger('status_shipping_details')->default(0);
            $table->tinyInteger('status_received')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('status_sent_to_supplier');
            $table->dropColumn('status_confirmed_by_supplier');
            $table->dropColumn('status_shipping_details');
            $table->dropColumn('status_received');
        });
    }
};
