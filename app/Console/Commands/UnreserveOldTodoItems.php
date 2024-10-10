<?php

namespace App\Console\Commands;

use App\Services\Todo\TodoService;
use Illuminate\Console\Command;

class UnreserveOldTodoItems extends Command
{
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
        $todoService = new TodoService();
        $todoService->unreserveOldItems();
    }
}
