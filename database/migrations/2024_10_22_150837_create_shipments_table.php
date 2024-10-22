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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('number');
            $table->string('type')->nullable()->default(null);
            $table->string('status')->nullable()->default(null);
            $table->tinyInteger('on_hold')->default(0);
            $table->string('date')->nullable()->default(null);
            $table->string('customer_number')->nullable()->default(null);
            $table->unsignedInteger('delivery_address_id')->default(0);
            $table->string('name')->nullable()->default(null);
            $table->string('attention')->nullable()->default(null);
            $table->string('email')->nullable()->default(null);
            $table->string('phone')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
