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
        Schema::table('shipment_lines', function (Blueprint $table) {
            $table->index('shipment_id');
            $table->index('article_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table) {
            $table->dropIndex('shipment_id');
            $table->dropIndex('article_number');
        });
    }
};
