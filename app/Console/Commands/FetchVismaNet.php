<?php

namespace App\Console\Commands;

use App\Http\Controllers\VismaNetController;
use Illuminate\Console\Command;

class FetchVismaNet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visma:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches new data from Visma.net';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $vismaNetController = new VismaNetController();
        $vismaNetController->fetchAll();
    }
}
