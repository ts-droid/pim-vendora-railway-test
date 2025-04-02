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
            $table->tinyInteger('is_operating_cost')->default(0)->after('show_sales_dashboard');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_people', function (Blueprint $table) {
            $table->dropColumn('is_operating_cost');
        });
    }
};
