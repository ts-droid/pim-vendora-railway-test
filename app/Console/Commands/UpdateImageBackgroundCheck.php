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

        foreach ($images as $image) {
            $this->output->writeln($image->filename . ': Processing...');

            $content = DoSpacesController::getContent($image->filename);

            switch ($processType) {
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

            $image->update([
                'solid_background' => $solidBackground ? 1 : 0
            ]);

            $this->output->write("\033[1A\033[K"); // Move cursor up and clear line
            $this->output->writeln($image->filename . ': ' . ($solidBackground ? 'SOLID' : 'NOT SOLID'));
        }

        $this->info('DONE!');
    }
}
