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
        Schema::table('customer_invoice_lines', function (Blueprint $table) {
            $table->index('article_number');
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->index('article_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_invoice_lines', function (Blueprint $table) {
            $table->dropIndex('article_number');
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropIndex('article_number');
        });
    }
};
