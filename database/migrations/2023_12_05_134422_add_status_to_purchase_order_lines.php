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
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->tinyInteger('is_completed')->default(0);
            $table->tinyInteger('is_canceled')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn('is_completed');
            $table->dropColumn('is_canceled');
        });
    }
};
