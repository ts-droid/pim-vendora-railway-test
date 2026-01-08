<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Models\TodoItem;
use App\Services\Todo\TodoItemService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateTodoItems extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'todo:generate-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate TODO items.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting TODO item generation.', $this->commandLogContext());

        // Generate collect article todos
        $created = $this->generateCollectArticleTodos();

        action_log('Finished TODO item generation.', $this->commandLogContext([
            'collect_article_created' => $created,
        ]));
    }

    private function generateCollectArticleTodos(): int
    {
        $existingTodoItems = TodoItem::whereNull('completed_at')
            ->where('type', '=', 'collect_article')
            ->get();

        $existingArticleIDs = [];
        if ($existingTodoItems) {
            foreach ($existingTodoItems as $todoItem) {
                $existingArticleIDs[] = $todoItem->data['article_id'] ?? 0;
            }
        }

        $excludeList = [
            'DWG'
        ];

        $articleIDs = DB::table('articles')
            ->select('id')
            ->where('status', '=', 'Active')
            ->whereNotIn('article_number', $excludeList)
            ->whereNotIn('id', $existingArticleIDs)
            ->where(function($query) {
                $query->where('article_number', '=', '')
                    ->orWhereNull('article_number')
                    ->orWhere('ean', '=', '')
                    ->orWhereNull('ean')
                    ->orWhere('description', '=', '')
                    ->orWhereNull('description')
                    ->orWhere('width', '=', 0)
                    ->orWhereNull('width')
                    ->orWhere('height', '=', 0)
                    ->orWhereNull('height')
                    ->orWhere('depth', '=', 0)
                    ->orWhereNull('depth')
                    ->orWhere('weight', '=', 0)
                    ->orWhereNull('weight')
                    ->orWhere('inner_box', '=', 0)
                    ->orWhereNull('inner_box')
                    ->orWhere('master_box', '=', 0)
                    ->orWhereNull('master_box')
                    ->orWhere('package_image_front', '=', '')
                    ->orWhereNull('package_image_front')
                    ->orWhere('package_image_back', '=', '')
            ->orWhereNull('package_image_back');
            })
            ->pluck('id');

        if (!$articleIDs) {
            return 0;
        }

        $todoItemService = new TodoItemService();
        $created = 0;

        foreach ($articleIDs as $articleID) {
            $todoItemService->createCollectArticle($articleID, 'all', 0, 'system');
            $created++;
        }

        return $created;
    }
}
