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
        Schema::create('article_prices', function (Blueprint $table) {
            $table->id();
            $table->string('article_number');
            $table->integer('customer_id');
            $table->float('base_price_SEK')->default(0);
            $table->float('base_price_EUR')->default(0);
            $table->float('base_price_DKK')->default(0);
            $table->float('base_price_NOK')->default(0);
            $table->float('percent');
            $table->float('percent_inner');
            $table->float('percent_master');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_prices');
    }
};
