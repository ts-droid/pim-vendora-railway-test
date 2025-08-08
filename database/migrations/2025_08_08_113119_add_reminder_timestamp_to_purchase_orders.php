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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('confirm_reminder_sent_at')->nullable()->default(null);
            $table->timestamp('shipping_reminder_sent_at')->nullable()->default(null);
            $table->timestamp('invoice_reminder_sent_at')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('confirm_reminder_sent_at');
            $table->dropColumn('shipping_reminder_sent_at');
            $table->dropColumn('invoice_reminder_sent_at');
        });
    }
};
