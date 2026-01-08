<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Models\TodoItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HoldTodoItems extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'todo:hold-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hold/Unhold TODO items';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting TODO hold evaluation.', $this->commandLogContext());

        $activeItems = TodoItem::whereNull('reserved_at')
            ->whereNull('completed_at')
            ->get();

        if (!$activeItems->count()) {
            action_log('No active TODO items to evaluate.', $this->commandLogContext());
            return;
        }

        $held = 0;
        $released = 0;

        foreach ($activeItems as $item) {
            $articleID = $item->data['article_id'] ?? null;
            if ($articleID) {
                // Check if the article has stock
                $article = DB::table('articles')
                    ->select('stock', 'status')
                    ->where('id', $articleID)
                    ->first();

                if ($article) {
                    if ($article->stock <= 0 || in_array($article->status, ['NoPurchases', 'Inactive'])) {
                        // Hold item
                        $item->update(['on_hold' => 1]);
                        $held++;
                    }
                    else {
                        // Unhold item
                        $item->update(['on_hold' => 0]);
                        $released++;
                    }
                }
            }

        }

        action_log('Finished TODO hold evaluation.', $this->commandLogContext([
            'items_checked' => $activeItems->count(),
            'items_held' => $held,
            'items_released' => $released,
        ]));
    }
}
