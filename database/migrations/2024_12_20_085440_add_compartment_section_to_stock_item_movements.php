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
        Schema::table('stock_item_movements', function (Blueprint $table) {
            $table->string('from_compartment_section')->nullable()->default(null)->after('from_stock_place_compartment');
            $table->string('to_compartment_section')->nullable()->default(null)->after('to_stock_place_compartment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_item_movements', function (Blueprint $table) {
            $table->dropColumn('from_compartment_section');
            $table->dropColumn('to_compartment_section');
        });
    }
};
