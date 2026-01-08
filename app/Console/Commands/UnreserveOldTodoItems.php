<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\Todo\TodoService;
use Illuminate\Console\Command;

class UnreserveOldTodoItems extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'todo:unreserve-old';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unreserve old reserved uncompleted todo items';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting unreserve old TODO items.', $this->commandLogContext());

        $todoService = new TodoService();
        $released = $todoService->unreserveOldItems();

        action_log('Finished unreserve old TODO items.', $this->commandLogContext([
            'items_unreserved' => $released,
        ]));
    }
}
