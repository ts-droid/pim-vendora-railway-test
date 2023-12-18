<?php

namespace App\Console\Commands;

use App\Services\BestsellerCalculator;
use Illuminate\Console\Command;

class CalculateBestsellers extends Command
{
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
        $calculator = new BestsellerCalculator();
        $calculator->calculateBestsellers();
    }
}
