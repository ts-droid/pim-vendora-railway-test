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
        Schema::table('stock_places', function (Blueprint $table) {
            $table->tinyInteger('is_virtual')->default(0);
            $table->tinyInteger('is_temporary')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_places', function (Blueprint $table) {
            $table->dropColumn('is_virtual');
            $table->dropColumn('is_temporary');
        });
    }
};
