<?php

namespace App\Services\Todo;

use App\Enums\TodoQueue;
use App\Enums\TodoType;
use App\Models\Article;
use App\Models\TodoItem;
use Illuminate\Support\Facades\DB;

class TodoItemService extends TodoService
{
    private TodoQueue $queue = TodoQueue::WMS;

    public function createCollectArticle(int $articleID, string $variant, int $createdBy): TodoItem
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
            $createdBy
        );
    }

    public function submitCollectArticle(TodoItem $todoItem, array $data): array
    {
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
            // TODO: Add support for package images
        ];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
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

        Article::where('id', $articleID)->update($updateData);

        return [
            'success' => true,
            'error' => '',
        ];
    }
}
