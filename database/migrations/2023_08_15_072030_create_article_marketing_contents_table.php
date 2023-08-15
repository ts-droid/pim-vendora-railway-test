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
        Schema::create('article_marketing_contents', function (Blueprint $table) {
            $table->id();
            $table->string('title_sv')->default('');
            $table->string('title_en')->default('');
            $table->string('title_da')->default('');
            $table->longText('system')->nullable()->default(null);
            $table->longText('message')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_marketing_contents');
    }
};
