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
        Schema::create('article_attributes', function (Blueprint $table) {
            $table->unsignedBigInteger('article_id');
            $table->string('attribute');
            $table->string('value');
            $table->timestamps();

            $table->unique(['article_id', 'attribute'], 'article_attribute_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_attributes');
    }
};
