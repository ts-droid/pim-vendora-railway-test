<?php

namespace App\Console\Commands;

use App\Console\Concerns\ProvidesCommandLogContext;
use App\Http\Controllers\DoSpacesController;
use App\Models\ArticleImage;
use Illuminate\Console\Command;

class UploadLocalFilesToStorage extends Command
{
    use ProvidesCommandLogContext;

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
        action_log('Starting upload of local files to storage.', $this->commandLogContext());

        $articleImages = ArticleImage::all();

        if (!$articleImages->count()) {
            $this->error('No article images found. Exiting...');
            action_log('No article images found for upload.', $this->commandLogContext(), 'warning');
            return;
        }

        $uploaded = 0;
        $missing = 0;

        foreach ($articleImages as $articleImage) {
            $filename = $articleImage->filename;
            $localPath = storage_path('app/public/' . $filename);

            if (!file_exists($localPath)) {
                $this->error('File not found: ' . $localPath);
                $missing++;
                action_log('Missing local file for upload.', $this->commandLogContext([
                    'filename' => $filename,
                    'local_path' => $localPath,
                ]), 'warning');
                continue;
            }

            $this->line('Uploading file: ' . $localPath);

            $spaceFilename = DoSpacesController::store($filename, file_get_contents($localPath), true);

            $articleImage->update([
                'path_url' => DoSpacesController::getURL($spaceFilename),
            ]);
            $uploaded++;
        }

        action_log('Finished upload of local files to storage.', $this->commandLogContext([
            'processed' => $articleImages->count(),
            'uploaded' => $uploaded,
            'missing' => $missing,
        ]));
    }
}
