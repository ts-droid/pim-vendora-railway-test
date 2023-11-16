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
        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->string('sales_order_id')->index();
            $table->integer('line_number')->index();
            $table->string('article_number');
            $table->string('invoice_number')->default('');
            $table->string('sales_person')->default('');
            $table->integer('quantity');
            $table->double('unit_cost');
            $table->double('unit_price');
            $table->string('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_lines');
    }
};
