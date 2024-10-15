<?php

namespace App\Console\Commands;

use App\Models\TodoItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HoldTodoItems extends Command
{
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
        $activeItems = TodoItem::whereNull('reserved_at')
            ->whereNull('completed_at')
            ->get();

        if ($activeItems) {
            return;
        }

        foreach ($activeItems as $item) {
            $articleID = $item->data['article_id'] ?? null;
            if ($articleID) {
                // Check if the article has stock
                $stock = DB::table('articles')
                    ->select('stock')
                    ->where('article_id', $articleID)
                    ->pluck('stock')
                    ->first();

                if ($stock > 0) {
                    // Unhold item
                    $item->update(['on_hold' => 0]);
                }
                else {
                    // Hold item
                    $item->update(['on_hold' => 1]);
                }
            }

        }
    }
}
