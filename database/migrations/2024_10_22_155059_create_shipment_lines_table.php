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
        Schema::create('shipment_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('shipment_id');
            $table->integer('line_number');
            $table->string('order_number')->nullable()->default(null);
            $table->string('article_number')->nullable()->default(null);
            $table->string('description')->nullable()->default(null);
            $table->integer('quantity')->default(0);
            $table->integer('shipped_quantity')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_lines');
    }
};
