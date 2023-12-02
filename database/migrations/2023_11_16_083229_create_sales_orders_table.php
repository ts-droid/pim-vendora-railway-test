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
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_type')->index();
            $table->string('order_number')->index();
            $table->string('status');
            $table->string('invoice_number')->default('');
            $table->string('sales_person')->default('');
            $table->string('date')->index();
            $table->string('customer')->default('')->index();
            $table->string('currency');
            $table->double('order_total')->index();
            $table->integer('order_total_quantity')->default(0)->index();
            $table->double('exchange_rate')->index();
            $table->text('note')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
