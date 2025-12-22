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
        Schema::create('ledger_account', function (Blueprint $table) {
            $table->string('number')->unique();
            $table->string('type')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('account_number');
            $table->string('date');
            $table->string('period');
            $table->string('module')->nullable();
            $table->string('description')->nullable();
            $table->float('debit')->default(0);
            $table->float('credit')->default(0);
            $table->string('currency')->default('SEK');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_account');
        Schema::dropIfExists('ledger_transactions');
    }
};
