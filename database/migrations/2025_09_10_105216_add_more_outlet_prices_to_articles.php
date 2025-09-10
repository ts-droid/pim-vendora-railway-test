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
            $table->string('outlet_price_mode')->default('Relative');
            $table->integer('outlet_price')->default(0);
            $table->integer('outlet_max_price')->default(0);
            $table->integer('outlet_price_fixed')->default(0);
            $table->integer('outlet_inner_price_fixed')->default(0);
            $table->integer('outlet_master_price_fixed')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('outlet_price_mode');
            $table->dropColumn('outlet_price');
            $table->dropColumn('outlet_max_price');
            $table->dropColumn('outlet_price_fixed');
            $table->dropColumn('outlet_inner_price_fixed');
            $table->dropColumn('outlet_master_price_fixed');
        });
    }
};
