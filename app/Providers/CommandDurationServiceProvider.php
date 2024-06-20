<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
use Laravel\Pulse\Facades\Pulse;

class CommandDurationServiceProvider extends ServiceProvider
{
    protected $startTimes = [];
    protected $threshold = 60;

    protected $blackList = [
        'queue:work',
        'pulse:check',
        'schedule:run'
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            if (in_array($event->command, $this->blackList)) {
                return;
            }

            $command = $this->getFullCommand($event);

            $this->startTimes[$command] = microtime(true);
        });

        Event::listen(CommandFinished::class, function(CommandFinished $event) {
            if (in_array($event->command, $this->blackList)) {
                return;
            }

            $command = $this->getFullCommand($event);

            if (isset($this->startTimes[$command])) {
                $duration = microtime(true) - $this->startTimes[$command];

                if ($duration > $this->threshold) {
                    Pulse::record('command_duration', $command, $duration)
                        ->avg()
                        ->max()
                        ->count();
                }

                unset($this->startTimes[$command]);
            }
        });
    }

    private function getFullCommand($event): string
    {
        $commandName = $event->command;

        $input = $event->input;
        $arguments = $input->getArguments();

        $command = $commandName;

        foreach ($arguments as $name => $value) {
            if ($value == $commandName) {
                continue;
            }

            if (!is_null($value)) {
                $command .= ' ' . $value;
            }
        }

        return $command;
    }
}
