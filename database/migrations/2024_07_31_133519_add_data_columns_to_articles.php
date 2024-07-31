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
            $table->float('product_height')->default(0);
            $table->float('product_width')->default(0);
            $table->float('product_depth')->default(0);
            $table->integer('product_weight')->default(0);
            $table->integer('serial_number_management')->default(0);
            $table->string('un_code')->default('');
            $table->integer('is_backorder')->default(0);
            $table->integer('minimum_order_quantity')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('product_height');
            $table->dropColumn('product_width');
            $table->dropColumn('product_depth');
            $table->dropColumn('product_weight');
            $table->dropColumn('serial_number_management');
            $table->dropColumn('un_code');
            $table->dropColumn('is_backorder');
            $table->dropColumn('minimum_order_quantity');
        });
    }
};
