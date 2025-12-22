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
        Schema::table('ledger_account_transactions', function (Blueprint $table) {
            $table->decimal('debit', 15, 2)->default(0)->change();
            $table->decimal('credit', 15, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ledger_account_transactions', function (Blueprint $table) {
            $table->float('debit')->default(0)->change();
            $table->float('credit')->default(0)->change();
        });
    }
};
