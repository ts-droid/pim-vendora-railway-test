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
        return view('livewire.pulse.slow-commands', [
            'slowCommands' => $this->aggregate('command_duration', ['avg', 'max', 'count'])
        ]);
    }
}
