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
        Schema::table('stock_items', function (Blueprint $table) {
            $table->index('article_number');
            $table->index('stock_place_compartment_id');
            $table->index(['article_number', 'stock_place_compartment_id'], 'article_stock_compartment_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropIndex('article_number');
            $table->dropIndex('stock_place_compartment_id');
            $table->dropIndex('article_stock_compartment_index');
        });
    }
};
