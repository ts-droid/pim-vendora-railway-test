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
        Schema::table('stock_place_groups', function (Blueprint $table) {
            $table->float('max_volume_class_size_a')->default(null)->nullable();
            $table->float('max_volume_class_size_b')->default(null)->nullable();
            $table->float('max_volume_class_size_c')->default(null)->nullable();
            $table->tinyInteger('wms_multi_intelligence')->default(null)->nullable();
            $table->integer('wms_multi_intelligence_period')->default(null)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_place_groups', function (Blueprint $table) {
            $table->dropColumn('max_volume_class_size_a');
            $table->dropColumn('max_volume_class_size_b');
            $table->dropColumn('max_volume_class_size_c');
            $table->dropColumn('wms_multi_intelligence');
            $table->dropColumn('wms_multi_intelligence_period');
        });
    }
};
