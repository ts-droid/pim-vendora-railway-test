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
            $table->integer('total_sales')->default(0)->after('is_webshop');
            $table->integer('total_sales_year_0')->default(0)->after('sales_last_year');
            $table->integer('total_sales_year_1')->default(0)->after('total_sales_year_0');
            $table->integer('total_sales_year_2')->default(0)->after('total_sales_year_1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('total_sales');
            $table->dropColumn('total_sales_year_0');
            $table->dropColumn('total_sales_year_1');
            $table->dropColumn('total_sales_year_2');
        });
    }
};
