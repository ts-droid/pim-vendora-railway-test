<?php

namespace App\Console\Commands;

use App\Services\Allianz\AllianzGradeCover;
use Illuminate\Console\Command;

class FetchAllianzGrades extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'allianz:fetch-grades';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch the grade for all companies from the allianz API.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $allianz = new AllianzGradeCover();
        $allianz->importGradeCover();
    }
}
