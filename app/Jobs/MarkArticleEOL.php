<?php

namespace App\Jobs;

use App\Http\Controllers\WgrController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarkArticleEOL implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $articleNumbers
    )
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$this->articleNumbers) {
            return;
        }

        $wgrController = new WgrController();

        // Mark articles as EOL in WGR
        foreach ($this->articleNumbers as $articleNumber) {
            $wgrController->updateArticle($articleNumber, [
                'eol' => true
            ]);
        }
    }
}
