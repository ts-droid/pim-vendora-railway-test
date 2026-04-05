<?php

namespace App\Console\Commands;

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\RawDataController;
use App\Models\Article;
use App\Models\ArticleMetaData;
use App\Services\AI\AIService;
use App\Utilities\MetaDataStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateUseCases extends Command
{
    const BATCH_SIZE = 5000;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:generate-use-cases';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate use cases for articles';

    private AIService $aiService;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->aiService = new AIService('claude-sonnet-4-6'); // TODO: Load model from some config

        $batchId = ConfigController::getConfig('generate_use_cases_batch_id');

        if ($batchId) {
            $this->processBatch($batchId);
        } else {
            $this->startBatch();
        }
    }

    private function startBatch()
    {
        $articleIDs = DB::table('articles')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('article_meta_data')
                    ->whereColumn('article_meta_data.article_id', 'articles.id')
                    ->where('article_meta_data.type', 'designed_for');
            })
            ->where('articles.status', '!=', 'Inactive')
            ->where('articles.shop_title_en', '!=', '')
            ->whereNotNull('articles.shop_title_en')
            ->where('articles.shop_marketing_description_en', '!=', '')
            ->whereNotNull('articles.shop_marketing_description_en')
            ->where('articles.shop_description_en', '!=', '')
            ->whereNotNull('articles.shop_description_en')
            ->limit(self::BATCH_SIZE)
            ->pluck('id');

        if (count($articleIDs) === 0) {
            return;
        };

        $promptController = new PromptController();
        $prompt = $promptController->getBySystemCode('article_use_cases');

        $batchRequests = [];

        foreach ($articleIDs as $articleID) {
            $article = Article::find($articleID);
            if (!$article) continue;

            $inputs = [
                'raw_data' => RawDataController::getArticleRaw($article, true, true, true, 'en')
            ];

            $system = $promptController->replaceInputs($prompt->system, $inputs);
            $message = $promptController->replaceInputs($prompt->message, $inputs);

            $batchRequests[] = [
                'system' => $system,
                'message' => $message,
                'meta_data' => [
                    'article_id' => $article->id
                ]
            ];
        }

        if (count($batchRequests) === 0) {
            return;
        }

        $response = $this->aiService->createMessageBatch($batchRequests);
        $batchId = $response['id'] ?? '';

        ConfigController::setConfigs(['generate_use_cases_batch_id' => $batchId]);

        $this->info('Started new batch (size: ' . count($batchRequests) . ')');
    }

    private function processBatch(string $batchId)
    {
        $status = $this->aiService->getMessageBatch($batchId);
        if ($status['processing_status'] !== 'ended') {
            $this->info('Waiting for batch to complete.');
            return;
        }

        $results = $this->aiService->getBatchTexts($batchId);
        foreach ($results as $customID => $output) {
            $metaDataKey = 'aibatch:' . $customID;
            $metaData = MetaDataStorage::get($metaDataKey);

            if (empty($metaData)) {
                MetaDataStorage::delete($metaDataKey);
                continue;
            }

            try {
                $output = str_replace('```json', '', $output);
                $output = str_replace('```', '', $output);
                $outputArray = json_decode($output, true);
            } catch (\Throwable $e) {
                $outputArray = [];
            }

            if (!is_array($outputArray) || !is_array($outputArray['designed_for']) || !is_array($outputArray['use_cases'])) {
                MetaDataStorage::delete($metaDataKey);
                continue;
            }

            // Create designed for
            foreach ($outputArray['designed_for'] as $item) {
                ArticleMetaData::create([
                    'article_id' => $metaData['article_id'],
                    'type' => 'designed_for',
                    'value_en' => $item
                ]);
            }

            // Create use cases
            foreach ($outputArray['use_cases'] as $item) {
                ArticleMetaData::create([
                    'article_id' => $metaData['article_id'],
                    'type' => 'use_cases',
                    'value_en' => $item
                ]);
            }

            MetaDataStorage::delete($metaDataKey);
        }

        ConfigController::setConfigs(['generate_use_cases_batch_id' => '']);

        $this->info('Finished processing batch.');
    }
}
