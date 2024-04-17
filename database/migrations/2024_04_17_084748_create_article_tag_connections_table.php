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
        Schema::create('article_tag_connections', function (Blueprint $table) {
            $table->integer('article_id')->unsigned();
            $table->integer('article_tag_id')->unsigned();
            $table->unique(['article_id', 'article_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_tag_connections');
    }
};
