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
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->index(['has_sync_error', 'created_at'], 'sales_orders_sync_error_created_at_idx');
            $table->index(['source', 'created_at'], 'sales_orders_source_created_at_idx');
            $table->index('phone', 'sales_orders_phone_idx');
            $table->index('email', 'sales_orders_email_idx');
            $table->index('payment_reference', 'sales_orders_payment_reference_idx');
            $table->index('customer_ref_no', 'sales_orders_customer_ref_no_idx');
            $table->index('billing_address_id', 'sales_orders_billing_address_id_idx');
            $table->index('shipping_address_id', 'sales_orders_shipping_address_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex('sales_orders_sync_error_created_at_idx');
            $table->dropIndex('sales_orders_source_created_at_idx');
            $table->dropIndex('sales_orders_phone_idx');
            $table->dropIndex('sales_orders_email_idx');
            $table->dropIndex('sales_orders_payment_reference_idx');
            $table->dropIndex('sales_orders_customer_ref_no_idx');
            $table->dropIndex('sales_orders_billing_address_id_idx');
            $table->dropIndex('sales_orders_shipping_address_id_idx');
        });
    }
};
