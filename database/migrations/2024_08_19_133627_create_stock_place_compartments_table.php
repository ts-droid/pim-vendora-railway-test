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
        Schema::create('stock_place_compartments', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique();
            $table->unsignedInteger('stock_place_id');
            $table->float('width');
            $table->float('height');
            $table->float('depth');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_place_compartments');
    }
};
