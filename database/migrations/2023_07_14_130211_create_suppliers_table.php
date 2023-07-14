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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->default(null);
            $table->string('number')->nullable()->default(null);
            $table->string('vat_number')->nullable()->default(null);
            $table->string('org_number')->nullable()->default(null);
            $table->string('name')->nullable()->default(null);
            $table->string('class_description')->nullable()->default(null);
            $table->string('credit_terms_description')->nullable()->default(null);
            $table->string('currency')->nullable()->default(null);
            $table->string('language')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
