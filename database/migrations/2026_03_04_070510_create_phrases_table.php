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
            $table->string('text_da');
            $table->string('text_en');
            $table->string('text_et');
            $table->string('text_fi');
            $table->string('text_lt');
            $table->string('text_lv');
            $table->string('text_no');
            $table->string('text_sv');
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
