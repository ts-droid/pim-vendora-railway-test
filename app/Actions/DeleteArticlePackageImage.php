<?php

namespace App\Actions;

use App\Http\Controllers\DoSpacesController;
use App\Models\Article;
use Illuminate\Support\Facades\DB;

class DeleteArticlePackageImage
{
    const IMAGE_TYPE_FRONT = 0;
    const IMAGE_TYPE_BACK = 1;
    const IMAGE_TYPE_BOTH = 2;

    public function execute(int $articleID, int $imageType): void
    {
        if ($imageType === self::IMAGE_TYPE_FRONT || $imageType === self::IMAGE_TYPE_BOTH) {
            $this->deleteFrontImage($articleID);
        }

        if ($imageType === self::IMAGE_TYPE_BACK || $imageType === self::IMAGE_TYPE_BOTH) {
            $this->deleteBackimage($articleID);
        }
    }

    private function deleteFrontImage(int $articleID): void
    {
        $this->deleteImage($articleID, 'package_image_front', 'package_image_front_url', 'front');
    }

    private function deleteBackimage(int $articleID): void
    {
        $this->deleteImage($articleID, 'package_image_back', 'package_image_back_url', 'back');
    }

    private function deleteImage(int $articleID, string $column, string $urlColumn, string $imageType): void
    {
        $image = (string) DB::table('articles')
            ->select([$column])
            ->where('id', $articleID)
            ->pluck($column)
            ->first();

        if ($image) {
            DoSpacesController::delete($image);

            Article::where('id', '=', $articleID)
                ->update([
                    $column => '',
                    $urlColumn => ''
                ]);

            action_log('Deleted article package image.', [
                'article_id' => $articleID,
                'column' => $column,
                'image_type' => $imageType
            ]);
        } else {
            action_log('No package image found to delete.', [
                'article_id' => $articleID,
                'column' => $column,
                'image_type' => $imageType
            ], 'warning');
        }
    }
}
