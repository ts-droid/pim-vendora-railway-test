<?php

namespace App\Console\Commands;

use App\Services\Todo\TodoService;
use Illuminate\Console\Command;

class DeleteTmpTodoItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'todo:delete-tmp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete uncompleted tmp items';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = new TodoService();
        $service->deleteTmpItems();
    }
}
