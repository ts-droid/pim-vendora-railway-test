<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateArticleJob;
use App\Services\Models\ArticleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArticleSyncController extends Controller
{
    public function syncArticle(Request $request)
    {
        $articleNumber = $request->get('articlenumber');
        if (!$articleNumber) {
            die('Missing parameter "articlenumber".');
        }

        $articleID = DB::table('articles')->where('article_number', $articleNumber)->value('id');

        if (!$articleID) {
            die('Article not found');
        }

        $articleService = new ArticleService();

        $job = new UpdateArticleJob($articleID, false);
        $job->handle($articleService);

        die('Article sync completed!');
    }

    public function syncAllArticles()
    {
        // Remove all jobs
        DB::table('jobs')->where('queue', 'article-sync')->delete();

        // Set sync status
        DB::table('articles')->where('is_syncing', 1)->update(['is_syncing' => 1]);

        // Queue all articles
        $articleIDs = DB::table('articles')
            ->whereIn('status', ['Active', 'NoPurchases'])
            ->pluck('id');

        $count = 0;

        foreach ($articleIDs as $articleID) {
            UpdateArticleJob::dispatch($articleID, false)->onQueue('article-sync');

            $count++;
        }

        die('Queued ' . $count . ' articles to sync.');
    }
}
