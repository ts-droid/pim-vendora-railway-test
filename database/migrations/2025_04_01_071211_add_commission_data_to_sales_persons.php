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
        Schema::table('sales_people', function (Blueprint $table) {
            $table->integer('basal_compensation')->default(0);
            $table->float('commission')->default(0);
            $table->integer('sample_amount')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_people', function (Blueprint $table) {
            $table->dropColumn('basal_compensation');
            $table->dropColumn('commission');
            $table->dropColumn('sample_amount');
        });
    }
};
