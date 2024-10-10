<?php

namespace App\Services\Todo;

use App\Actions\UploadArticlePackageImage;
use App\Enums\TodoQueue;
use App\Enums\TodoType;
use App\Models\Article;
use App\Models\TodoItem;
use App\Services\ImageQualityChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TodoItemService extends TodoService
{
    private TodoQueue $queue = TodoQueue::WMS;

    public function createCollectArticle(int $articleID, string $variant, int $createdBy, string $source): TodoItem
    {
        $article = DB::table('articles')->select('article_number')->where('id', $articleID)->first();
        $articleNumber = $article->article_number ?? '';

        switch ($variant) {
            case 'article_number':
                $title = 'Collect Article Number';
                break;

            case 'ean':
                $title = 'Collect Article EAN';
                break;

            case 'description':
                $title = 'Collect Article Name';
                break;

            case 'size':
                $title = 'Collect Article Size';
                break;

            case 'weight':
                $title = 'Collect Article Weight';
                break;

            case 'box_size':
                $title = 'Collect Article Box Size';
                break;

            case 'images':
                $title = 'Collect Article Images';
                break;

            default:
                $title = 'Collect Article Data';
                break;
        }

        return $this->createItem(
            $this->queue,
            TodoType::CollectArticle,
            $title,
            $articleNumber,
            [
                'article_id' => $articleID,
                'variant' => $variant,
            ],
            $createdBy,
            $source
        );
    }

    public function submitCollectArticle(TodoItem $todoItem, Request|array $data): array
    {
        $dataArray = $data instanceof Request ? $data->all() : $data;

        $packageImageFront = $data->hasFile('package_image_front') ? $data->file('package_image_front') : null;
        $packageImageBack = $data->hasFile('package_image_back') ? $data->file('package_image_back') : null;

        $articleID = $todoItem->data['article_id'] ?? 0;
        $updateData = [];

        $fields = [
            'article_number',
            'ean',
            'description',
            'width',
            'height',
            'depth',
            'weight',
            'inner_box',
            'master_box',
        ];

        foreach ($fields as $field) {
            if (isset($dataArray[$field])) {
                $updateData[$field] = $dataArray[$field];
            }
        }

        $variant = $todoItem->data['variant'] ?? '';
        switch ($variant) {
            case 'article_number':
                $requiredFields = ['article_number'];
                break;

            case 'ean':
                $requiredFields = ['ean'];
                break;

            case 'description':
                $requiredFields = ['description'];
                break;

            case 'size':
                $requiredFields = ['width', 'height', 'depth'];
                break;

            case 'weight':
                $requiredFields = ['weight'];
                break;

            case 'box_size':
                $requiredFields = ['inner_box', 'master_box'];
                break;

            case 'images':
                $requiredFields = [];

                if (!$packageImageFront || !$packageImageBack) {
                    return [
                        'success' => false,
                        'error' => 'Package images are required',
                    ];
                }
                break;

            default:
                $requiredFields = [];
                break;
        }

        foreach ($requiredFields as $requiredField) {
            if (!($updateData[$requiredField] ?? null)) {
                return [
                    'success' => false,
                    'error' => $requiredField . ' is required',
                ];
            }
        }

        // Save article data
        Article::where('id', $articleID)->update($updateData);

        // Upload new package images
        if ($packageImageFront) {
            $frontImageContent = @file_get_contents($packageImageFront->getRealPath());
            if ($frontImageContent) {
                (new UploadArticlePackageImage)->execute(
                    $articleID,
                    $packageImageFront->getClientOriginalName(),
                    $frontImageContent,
                    UploadArticlePackageImage::IMAGE_TYPE_FRONT
                );
            }
        }

        if ($packageImageBack) {
            $backImageContent = @file_get_contents($packageImageBack->getRealPath());
            if ($backImageContent) {
                (new UploadArticlePackageImage)->execute(
                    $articleID,
                    $packageImageBack->getClientOriginalName(),
                    $backImageContent,
                    UploadArticlePackageImage::IMAGE_TYPE_BACK
                );
            }
        }

        return [
            'success' => true,
            'error' => '',
        ];
    }
}
