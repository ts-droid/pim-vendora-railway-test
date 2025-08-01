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
        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('supplier_id');
            $table->string('name')->default('');
            $table->string('attention')->default('');
            $table->string('email')->default('');
            $table->string('phone1')->default('');
            $table->string('phone2')->default('');
            $table->string('address_line')->default('');
            $table->string('address_city')->default('');
            $table->string('address_country')->default('');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_contacts');
    }
};
