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
            $table->integer('sales_7_days_last_year')->default(0)->after('sales_180_days');
            $table->integer('sales_60_days_last_year')->default(0)->after('sales_7_days_last_year');
            $table->integer('sales_90_days_last_year')->default(0)->after('sales_60_days_last_year');
            $table->integer('sales_180_days_last_year')->default(0)->after('sales_90_days_last_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('sales_7_days_last_year');
            $table->dropColumn('sales_60_days_last_year');
            $table->dropColumn('sales_90_days_last_year');
            $table->dropColumn('sales_180_days_last_year');
        });
    }
};
