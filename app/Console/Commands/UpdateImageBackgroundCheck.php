<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Jobs\AnalyzeImage;
use App\Models\ArticleImage;
use App\Utilities\ImageBackgroundAnalyzer;
use Illuminate\Console\Command;

class UpdateImageBackgroundCheck extends Command
{
    use ProvidesCommandLogContext;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:background-check {processType?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reprocess all images to check whether they have a solid background or not.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $processType = $this->argument('processType') ?? '';
        $processType = $processType ?: 'advanced';

        action_log('Starting image background check queue.', $this->commandLogContext([
            'process_type' => $processType,
        ]));

        $images = ArticleImage::all();

        if (!$images->count()) {
            action_log('No article images found for background check.', $this->commandLogContext(), 'warning');
            return;
        }

        $count = 0;

        foreach ($images as $image) {
            AnalyzeImage::dispatch($image, $processType)->onQueue('low');
            $count++;
        }

        $this->info('Queue ' . $count . ' items for analyzing.');
        action_log('Queued images for background check.', $this->commandLogContext([
            'process_type' => $processType,
            'queued' => $count,
        ]));
    }
}
