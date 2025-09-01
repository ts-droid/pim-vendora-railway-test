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
            $table->float('stats_last_cost')->default(0);
            $table->float('stats_avg_cost')->default(0);
            $table->float('stats_min_cost')->default(0);
            $table->float('stats_max_cost')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('stats_last_cost');
            $table->dropColumn('stats_avg_cost');
            $table->dropColumn('stats_min_cost');
            $table->dropColumn('stats_max_cost');
        });
    }
};
