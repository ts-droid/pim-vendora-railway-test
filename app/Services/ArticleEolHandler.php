<?php

namespace App\Services;

use App\Http\Controllers\ConfigController;
use App\Models\Article;
use App\Services\VismaNet\VismaNetApiService;
use Illuminate\Support\Facades\DB;

class ArticleEolHandler
{
    /**
     * Deactivate all articles that have reached EOL and are not used in any ongoing orders.
     *
     * @return void
     */
    public function inactivateArticles() {
        $articleNumbers = DB::table('articles')
            ->select('article_number')
            ->where('status', 'NoPurchases')
            ->where('stock', '<=', 0)
            ->where('is_completed', 1)
            ->pluck('article_number')
            ->toArray();

        if (!$articleNumbers) {
            return;
        }

        foreach ($articleNumbers as $articleNumber) {
            $this->inactivateArticle($articleNumber);
        }
    }

    /**
     * Deactivate an article in Visma.net if it has reached EOL and is not used in any ongoing orders.
     *
     * @param string $articleNumber
     * @return void
     */
    public function inactivateArticle(string $articleNumber)
    {
        $article = Article::where('article_number', $articleNumber)->first();

        if (!$article) {
            return;
        }

        if ($article->status != 'NoPurchases') {
            return;
        }

        if ($article->stock > 0) {
            return;
        }

        // Check if there is any un-invoiced order lines
        $numOngoingLines = DB::table('sales_order_lines')
            ->where('article_number', $articleNumber)
            ->where(function($query) {
                $query->where('is_completed', 0)
                    ->orWhere('invoice_number', '')
                    ->orWhereNull('invoice_number');
            })
            ->count();

        if ($numOngoingLines > 0) {
            return;
        }

        // This article should be inactivated
        $vismaAPI = new VismaNetApiService();
        $vismaAPI->callAPI('PUT', '/v1/inventory/' . $articleNumber, [
            'status' => array('value' => 'Inactive')
        ]);
    }
}
