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
            $this->startTimes[$event->command] = microtime(true);
        });

        Event::listen(CommandFinished::class, function(CommandFinished $event) {
            if (isset($this->startTimes[$event->command])) {
                $duration = microtime(true) - $this->startTimes[$event->command];

                if ($duration > $this->threshold) {
                    Pulse::record('command_duration', $event->command, $duration)
                        ->avg()
                        ->max()
                        ->count();
                }

                unset($this->startTimes[$event->command]);
            }
        });
    }
}
