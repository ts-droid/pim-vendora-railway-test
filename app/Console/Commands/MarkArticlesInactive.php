<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;

class MarkArticlesInactive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:mark-inactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark EOL articles as inactive when stock is zero.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $articles = Article::where('status', '=', 'NoPurchases')
            ->where('stock', '<=', 0)
            ->limit(50)
            ->get();

        if (!$articles->count()) return;

        foreach ($articles as $article) {
            $article->update(['status' => 'Inactive']);
        }
    }
}
