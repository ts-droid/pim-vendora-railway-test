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
        $articleImages = ArticleImage::all();

        if (!$articleImages) {
            $this->error('No article images found. Exiting...');
        }

        foreach ($articleImages as $articleImage) {
            $filename = $articleImage->filename;
            $localPath = storage_path('app/public/' . $filename);

            if (!file_exists($localPath)) {
                $this->error('File not found: ' . $localPath);
                continue;
            }

            $this->line('Uploading file: ' . $localPath);

            $spaceFilename = DoSpacesController::store($filename, file_get_contents($localPath), true);

            $articleImage->update([
                'path_url' => DoSpacesController::getURL($spaceFilename),
            ]);
        }
    }
}
