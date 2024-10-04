<?php

namespace App\Actions;

use App\Http\Controllers\DoSpacesController;
use App\Models\Article;
use Illuminate\Support\Facades\DB;

class UploadArticlePackageImage
{
    const IMAGE_TYPE_FRONT = 0;
    const IMAGE_TYPE_BACK = 1;

    public function execute(int $articleID, string $filename, string $imageContent, int $imageType): void
    {
        $column = $imageType === self::IMAGE_TYPE_FRONT ? 'package_image_front' : 'package_image_back';
        $urlColumn = $column . '_url';

        // Delete the old file
        $oldImage = DB::table('articles')
            ->select([$column])
            ->where('id', $articleID)
            ->pluck($column)
            ->first();

        if ($oldImage) {
            DoSpacesController::delete($oldImage);
        }

        // Upload and store new file
        $remoteFilename = DoSpacesController::store($filename, $imageContent, true);

        Article::where('id', '=', $articleID)
            ->update([
                $column => $remoteFilename,
                $urlColumn => DoSpacesController::getURL($remoteFilename)
            ]);
    }
}
