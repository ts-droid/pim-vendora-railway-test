<?php

use App\Models\Article;
use App\Models\InventoryReceiptLine;
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
            $table->double('external_cost')->default(0)->after('cost_price_avg');
        });

        // Set value for all existing articles
        $articles = Article::all();

        if ($articles) {
            foreach ($articles as $article) {
                $receiptLine = InventoryReceiptLine::where('article_number', $article->article_number)
                    ->orderBy('updated_at', 'DESC')
                    ->first();

                if (!$receiptLine) {
                    continue;
                }

                $article->external_cost = $receiptLine->unit_cost;
                $article->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('external_cost');
        });
    }
};
