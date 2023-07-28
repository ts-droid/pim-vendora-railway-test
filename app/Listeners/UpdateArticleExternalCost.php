<?php

namespace App\Listeners;

use App\Events\InventoryReceiptUpdated;
use App\Http\Controllers\ArticleController;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateArticleExternalCost
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(InventoryReceiptUpdated $event): void
    {
        if (!$event->inventoryReceipt->lines) {
            return;
        }

        $articleController = new ArticleController();

        foreach ($event->inventoryReceipt->lines as $line) {
            $articleNumber = $line->article_number;
            $cost = (float) $line->unit_cost;

            $article = Article::where('article_number', $articleNumber)->first();

            if (!$article) {
                continue;
            }

            $articleController->update(new Request(['external_cost' => $cost]), $article);
        }
    }
}
