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
            $table->integer('outlet_rounding')->default(0);
            $table->string('outlet_mode')->default('standard');
            $table->integer('outlet_discount')->nullable()->default(null);
            $table->integer('outlet_max_discount')->nullable()->default(null);
            $table->integer('outlet_min_margin')->nullable()->default(null);
            $table->integer('outlet_inner_weight')->nullable()->default(null);
            $table->integer('outlet_master_weight')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('outlet_rounding');
            $table->dropColumn('outlet_mode');
            $table->dropColumn('outlet_discount');
            $table->dropColumn('outlet_max_discount');
            $table->dropColumn('outlet_min_margin');
            $table->dropColumn('outlet_inner_weight');
            $table->dropColumn('outlet_master_weight');
        });
    }
};
