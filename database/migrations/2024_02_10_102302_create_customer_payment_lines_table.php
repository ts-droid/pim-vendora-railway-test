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
        Schema::create('customer_payment_lines', function (Blueprint $table) {
            $table->id();
            $table->integer('customer_payment_id');
            $table->string('document_type');
            $table->string('reference_number');
            $table->float('amount_paid');
            $table->string('date');
            $table->string('due_date');
            $table->float('balance');
            $table->string('currency');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_payment_lines');
    }
};
