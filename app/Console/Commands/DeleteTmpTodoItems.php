<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\Todo\TodoService;
use Illuminate\Console\Command;

class DeleteTmpTodoItems extends Command
{
    use ProvidesCommandLogContext;

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
        action_log('Starting deletion of temporary TODO items.', $this->commandLogContext());

        $service = new TodoService();
        $deleted = $service->deleteTmpItems();

        action_log('Finished deletion of temporary TODO items.', $this->commandLogContext([
            'deleted_items' => $deleted,
        ]));
    }
}
