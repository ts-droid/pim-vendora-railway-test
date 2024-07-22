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
        Schema::create('credit_note_lines', function (Blueprint $table) {
            $table->id();
            $table->integer('credit_note_id');
            $table->string('line_key')->nullable()->default(null);
            $table->string('article_number')->nullable()->default(null);
            $table->string('description')->nullable()->default(null);
            $table->string('order_number')->nullable()->default(null);
            $table->string('shipment_number')->nullable()->default(null);
            $table->integer('quantity')->default(0);
            $table->double('unit_price')->default(0);
            $table->double('amount')->default(0);
            $table->double('cost')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_note_lines');
    }
};
