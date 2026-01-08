<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\BestsellerCalculator;
use Illuminate\Console\Command;

class CalculateBestsellers extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bestsellers:calculate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate the article bestsellers.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        action_log('Starting bestseller calculation.', $this->commandLogContext());

        $calculator = new BestsellerCalculator();
        $calculator->calculateBestsellers();

        action_log('Finished bestseller calculation.', $this->commandLogContext());
    }
}
