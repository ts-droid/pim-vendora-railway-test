<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Services\Allianz\AllianzGradeCover;
use Illuminate\Console\Command;

class FetchAllianzGrades extends Command
{
    use ProvidesCommandLogContext;

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
        action_log('Starting Allianz grade fetch.', $this->commandLogContext());

        $allianz = new AllianzGradeCover();
        $result = $allianz->importGradeCover();

        action_log('Finished Allianz grade fetch.', $this->commandLogContext([
            'result' => $result,
        ]));
    }
}
