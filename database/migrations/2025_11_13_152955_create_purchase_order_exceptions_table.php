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
        Schema::create('purchase_order_exceptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_shipment_id');
            $table->unsignedBigInteger('purchase_order_line_id');
            $table->integer('diff');
            $table->string('exception_type');
            $table->json('images');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_exceptions');
    }
};
