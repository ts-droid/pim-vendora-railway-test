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
        Schema::create('phrases', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('text_da')->nullable();
            $table->string('text_en')->nullable();
            $table->string('text_et')->nullable();
            $table->string('text_fi')->nullable();
            $table->string('text_lt')->nullable();
            $table->string('text_lv')->nullable();
            $table->string('text_no')->nullable();
            $table->string('text_sv')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phrases');
    }
};
