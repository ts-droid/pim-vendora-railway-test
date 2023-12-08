<?php

namespace App\Console\Commands;

use App\Http\Controllers\DoSpacesController;
use App\Models\ArticleImage;
use Illuminate\Console\Command;

class UploadLocalFilesToStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upload-local-files-to-storage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uploaded all local files to storage bucket.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->line('Scanning storage directory and uploading files...');

        DoSpacesController::storeLocalFiles();

        $this->info('Upload completed!');

        $this->line('Updating article images in database...');

        // Update article images in database
        $articleImages = ArticleImage::all();

        if ($articleImages) {
            foreach ($articleImages as $articleImage) {

                $publicURL = DoSpacesController::getURL('public/' . $articleImage->filename);

                $articleImage->update([
                    'path_url' => $publicURL
                ]);

            }
        }

        $this->info('Database update completed!');
    }
}
