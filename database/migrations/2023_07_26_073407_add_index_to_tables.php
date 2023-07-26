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
        Schema::table('customer_invoices', function (Blueprint $table) {
            $table->index('date');
            $table->index('customer_number');
        });

        Schema::table('customer_invoice_lines', function (Blueprint $table) {
            $table->index('customer_invoice_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('customer_number');
            $table->index('vat_number');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->index('number');
            $table->index('vat_number');
            $table->index('name');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->index('article_number');
            $table->index('supplier_number');
            $table->index('is_webshop');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index('date');
            $table->index('supplier_id');
            $table->index('supplier_number');
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->index('purchase_order_id');
        });

        Schema::table('inventory_receipts', function (Blueprint $table) {
            $table->index('date');
        });

        Schema::table('inventory_receipt_lines', function (Blueprint $table) {
            $table->index('inventory_receipt_id');
        });

        Schema::table('stock_logs', function (Blueprint $table) {
            $table->index('article_number');
        });

        Schema::table('currency_rates', function (Blueprint $table) {
            $table->index('from_currency');
            $table->index('to_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_invoices', function (Blueprint $table) {
            $table->dropIndex('date');
            $table->dropIndex('customer_number');
        });

        Schema::table('customer_invoice_lines', function (Blueprint $table) {
            $table->dropIndex('customer_invoice_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customer_number');
            $table->dropIndex('vat_number');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex('number');
            $table->dropIndex('vat_number');
            $table->dropIndex('name');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex('article_number');
            $table->dropIndex('supplier_number');
            $table->dropIndex('is_webshop');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('date');
            $table->dropIndex('supplier_id');
            $table->dropIndex('supplier_number');
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropIndex('purchase_order_id');
        });

        Schema::table('inventory_receipts', function (Blueprint $table) {
            $table->dropIndex('date');
        });

        Schema::table('inventory_receipt_lines', function (Blueprint $table) {
            $table->dropIndex('inventory_receipt_id');
        });

        Schema::table('stock_logs', function (Blueprint $table) {
            $table->dropIndex('article_number');
        });

        Schema::table('currency_rates', function (Blueprint $table) {
            $table->dropIndex('from_currency');
            $table->dropIndex('to_currency');
        });
    }
};
