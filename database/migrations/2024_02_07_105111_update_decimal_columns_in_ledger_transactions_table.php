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
        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->decimal('debit', 15, 2)->change();
            $table->decimal('credit', 15, 2)->change();
            $table->decimal('currency_rate', 15, 8)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_transactions', function (Blueprint $table) {
            $table->float('debit')->change();
            $table->float('credit')->change();
            $table->float('currency_rate')->change();
        });
    }
};
