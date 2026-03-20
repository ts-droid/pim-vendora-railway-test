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
        Schema::create('article_meta_data', function (Blueprint $table) {
            $table->id();
            $table->integer('article_id');
            $table->string('type');
            $table->text('value_sv')->nullable();
            $table->text('value_fr')->nullable();
            $table->text('value_de')->nullable();
            $table->text('value_lt')->nullable();
            $table->text('value_lv')->nullable();
            $table->text('value_et')->nullable();
            $table->text('value_is')->nullable();
            $table->text('value_fi')->nullable();
            $table->text('value_no')->nullable();
            $table->text('value_en')->nullable();
            $table->text('value_da')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_meta_data');
    }
};
