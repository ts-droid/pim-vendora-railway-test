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
        Schema::create('related_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('child_article_id')->constrained('articles')->cascadeOnDelete();
            $table->unique(['parent_article_id', 'child_article_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('related_articles');
    }
};
