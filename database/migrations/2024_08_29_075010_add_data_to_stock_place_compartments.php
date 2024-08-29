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
        Schema::table('stock_place_compartments', function (Blueprint $table) {
            $table->tinyInteger('is_truck')->default(0)->after('depth');
            $table->tinyInteger('is_movable')->default(0)->after('is_truck');
            $table->tinyInteger('is_walk_through')->default(0)->after('is_movable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_place_compartments', function (Blueprint $table) {
            $table->dropColumn('is_truck');
            $table->dropColumn('is_movable');
            $table->dropColumn('is_walk_through');
        });
    }
};
