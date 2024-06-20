<?php

namespace App\Livewire\Pulse;

use Illuminate\Contracts\View\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]

class SlowCommands extends Card
{
    public function render(): View
    {
        $slowCommands = $this->aggregate('command_duration', ['avg', 'max', 'count']);

        // Sort by max duration collection
        $slowCommands = $slowCommands->sortByDesc('max');

        // Format the seconds
        foreach ($slowCommands as &$command) {
            list($command->avg, $command->avg_unit) = $this->formatSeconds($command->avg);
            list($command->max, $command->max_unit) = $this->formatSeconds($command->max);
        }

        return view('livewire.pulse.slow-commands', [
            'slowCommands' => $slowCommands
        ]);
    }

    private function formatSeconds(int $seconds): array
    {
        $units = ['s', 'm', 'h'];
        $unit = 0;

        while ($seconds >= 60 && $unit < count($units) - 1) {
            $seconds /= 60;
            $unit++;
        }

        return [$seconds, $units[$unit]];
    }
}
