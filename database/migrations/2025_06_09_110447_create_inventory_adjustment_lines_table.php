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
        Schema::create('inventory_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('inventory_adjustment_id');
            $table->integer('line_number');
            $table->string('article_number');
            $table->integer('quantity');
            $table->float('unit_cost')->default(0);
            $table->float('ext_cost')->default(0);
            $table->string('reason_code');
            $table->string('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_lines');
    }
};
