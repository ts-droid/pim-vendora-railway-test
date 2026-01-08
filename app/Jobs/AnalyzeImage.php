<?php

namespace App\Jobs;

use App\Http\Controllers\DoSpacesController;
use App\Models\ArticleImage;
use App\Utilities\ImageBackgroundAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ArticleImage $image,
        public string $type
    )
    {
        action_log('Invoked job method.', [
            'job' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        action_log('Executing job handle method.', [
            'job' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ]);

        $content = DoSpacesController::getContent($this->image->filename);

        switch ($this->type) {
            case 'topbar':
                $solidBackground = ImageBackgroundAnalyzer::hasSolidBackground($content, 'topbar');
                break;

            case 'corners':
                $solidBackground = ImageBackgroundAnalyzer::hasSolidBackground($content, 'corners');
                break;

            case 'advanced':
            default:
                $solidBackground = ImageBackgroundAnalyzer::hasSolidBackgroundAdvanced($content);
                break;
        }

        $this->image->update([
            'solid_background' => $solidBackground ? 1 : 0
        ]);

        // Throttle to avoid excessive CPU usage
        sleep(1);
    }
}
