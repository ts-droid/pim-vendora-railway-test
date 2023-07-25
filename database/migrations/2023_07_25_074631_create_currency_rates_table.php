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
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->default(null);
            $table->string('from_currency')->nullable()->default(null);
            $table->string('to_currency')->nullable()->default(null);
            $table->string('type')->nullable()->default(null);
            $table->double('rate')->default(0);
            $table->string('date')->nullable()->default(null);
            $table->string('mult_div')->nullable()->default(null);
            $table->double('rate_reciprocal')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
