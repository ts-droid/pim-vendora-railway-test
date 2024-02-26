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
        Schema::create('sales_person_budget', function (Blueprint $table) {
            $table->id();
            $table->integer('sales_person_id');
            $table->integer('year');
            $table->integer('month');
            $table->integer('turnover');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_person_budget');
    }
};
