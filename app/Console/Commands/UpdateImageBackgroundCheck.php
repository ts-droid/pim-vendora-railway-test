<?php

namespace App\Console\Commands;

use App\Http\Controllers\DoSpacesController;
use App\Jobs\AnalyzeImage;
use App\Models\ArticleImage;
use App\Utilities\ImageBackgroundAnalyzer;
use Illuminate\Console\Command;

class UpdateImageBackgroundCheck extends Command
{
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
        $images = ArticleImage::all();

        $processType = $this->argument('processType') ?? '';
        $processType = $processType ?: 'advanced';

        $count = 0;

        foreach ($images as $image) {
            AnalyzeImage::dispatch($image, $processType)->onQueue('low');
            $count++;
        }

        $this->info('Queue ' . $count . ' items for analyzing.');
    }
}
