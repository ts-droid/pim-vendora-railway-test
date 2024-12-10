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
            $table->string('volume_class')->default('')->after('stock_place_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_place_compartments', function (Blueprint $table) {
            $table->dropColumn('volume_class');
        });
    }
};
