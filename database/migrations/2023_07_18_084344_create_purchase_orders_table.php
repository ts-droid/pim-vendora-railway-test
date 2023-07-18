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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('status')->nullable()->default(null);
            $table->string('date')->nullable()->default(null);
            $table->string('promised_date')->nullable()->default(null);
            $table->string('supplier_id')->nullable()->default(null);
            $table->string('supplier_number')->nullable()->default(null);
            $table->string('supplier_name')->nullable()->default(null);
            $table->string('currency')->nullable()->default(null);
            $table->double('amount')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
