<?php

namespace App\Console\Commands;

use App\Http\Controllers\DoSpacesController;
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
    protected $signature = 'images:background-check';

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

        foreach ($images as $image) {
            $this->line('Processing: ' . $image->filename);

            $content = DoSpacesController::getContent($image->filename);

            $solidBackground = ImageBackgroundAnalyzer::hasSolidBackground($content, 'topbar');

            $image->update([
                'solid_background' => $solidBackground ? 1 : 0
            ]);
        }

        $this->info('DONE!');
    }
}
