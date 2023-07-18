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
        Schema::create('inventory_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->integer('inventory_receipt_id');
            $table->string('line_key');
            $table->string('article_number')->nullable()->default(null);
            $table->string('description')->nullable()->default(null);
            $table->double('unit_cost')->default(0);
            $table->double('quantity')->default(0);
            $table->double('total_cost')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_receipt_lines');
    }
};
