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
            $table->string('color')->default('#878787')->after('map_size_y');
            $table->integer('type')->default(1)->after('color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_places', function (Blueprint $table) {
            $table->dropColumn('color');
            $table->dropColumn('type');
        });
    }
};
