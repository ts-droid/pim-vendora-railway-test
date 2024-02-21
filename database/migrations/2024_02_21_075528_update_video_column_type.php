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
        Schema::table('articles', function (Blueprint $table) {
            $table->text('video')->default(null)->nullable()->change();
        });

        // Update all videos to be stored as JSON
        $articles = \App\Models\Article::all();

        if ($articles) {
            foreach ($articles as $article) {
                if ($article->video) {
                    $article->update(['video' => json_encode([$article->video])]);
                }
                else {
                    $article->update(['video' => null]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('video')->nullable(false)->default('')->change();
        });
    }
};
