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
            $table->tinyInteger('is_manual')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_place_compartments', function (Blueprint $table) {
            $table->dropColumn('is_manual');
        });
    }
};
