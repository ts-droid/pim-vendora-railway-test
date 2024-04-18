<?php

namespace App\Http\Controllers;

use App\Models\ArticleMarketingContent;
use App\Models\Language;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarketingContentController extends Controller
{
    public function articleGet(Request $request)
    {
        $filter = $this->getModelFilter(ArticleMarketingContent::class, $request);

        $query = $this->getQueryWithFilter(ArticleMarketingContent::class, $filter);

        $articleMarketingContents = $query->get();

        return ApiResponseController::success($articleMarketingContents->toArray());
    }

    public function articleStore(Request $request)
    {
        $data = [
            'system' => ($request->system ?? ''),
            'message' => ($request->message ?? ''),
        ];

        $languages = (new LanguageController())->getAllLanguages();

        foreach ($languages as $language) {
            $data['title_' . $language->language_code] = ($request->{'title_' . $language->language_code} ?? '');
        }

        $articleMarketingContent = ArticleMarketingContent::create($data);

        return ApiResponseController::success($articleMarketingContent->toArray());
    }

    public function articleUpdate(Request $request, ArticleMarketingContent $articleMarketingContent)
    {
        $fillables = get_model_attributes(ArticleMarketingContent::class);

        foreach ($request->all() as $key => $value) {
            if (in_array($key, $fillables)) {
                $articleMarketingContent->{$key} = $value;
            }
        }

        $articleMarketingContent->save();

        return ApiResponseController::success($articleMarketingContent->toArray());
    }

    public function articleDelete(Request $request, ArticleMarketingContent $articleMarketingContent)
    {
        $articleMarketingContent->delete();

        return ApiResponseController::success();
    }

    public function blogPostStream(Request $request)
    {
        set_time_limit(0);

        while(ob_get_level() > 0) {
            ob_end_clean();
        }

        $languageCode = $request->json()->get('language_code', 'en');
        $title = $request->json()->get('title');
        $content = $request->json()->get('content');
        $site = $request->json()->get('site');

        $language = Language::where('language_code', $languageCode)->first();

        $languageTitle = $language ? $language->title : $languageCode;

        $variables = [
            'title' => $title,
            'content' => $content,
            'site' => $site,
            'language' => $languageTitle,
        ];

        $promptController = new PromptController();
        $prompt = $promptController->getBySystemCode('share_blog_post');

        $system = $this->replaceVariables($prompt->system, $variables);
        $message = $this->replaceVariables($prompt->message, $variables);

        $postData = [
            'model' => env('OPEN_AI_DEFAULT_MODEL', 'gpt-4-1106-preview'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $system,
                ],
                [
                    'role' => 'user',
                    'content' => $message,
                ]
            ],
            'stream' => true,
        ];

        // Stream the response from OpenAI to the client
        $response = new StreamedResponse(function() use ($postData) {
            $ch = curl_init((env('OPEN_AI_ENDPOINT') . '/chat/completions'));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . env('OPEN_AI_KEY')
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
                echo $data;
                ob_flush();
                flush();
                return strlen($data);
            });

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);

            curl_exec($ch);

            if (curl_errno($ch)) {
                dd('Curl error: ' . curl_error($ch));
            }

            curl_close($ch);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    public function articleStream(Request $request, ArticleMarketingContent $articleMarketingContent)
    {
        set_time_limit(0);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $languageCode = $request->json()->get('language_code', 'en');
        $productName = $request->json()->get('product_name');
        $productDescription = $request->json()->get('product_description');
        $brand = $request->json()->get('brand');

        $language = Language::where('language_code', $languageCode)->first();

        $languageTitle = $language ? $language->title : $languageCode;

        // Remove brand name from product title
        if ($brand) {
            $productName = trim(str_ireplace($brand, '', $productName));
        }

        $variables = [
            'product_name' => $productName,
            'product_description' => $productDescription,
            'brand' => $brand,
            'language' => $languageTitle,
        ];

        $system = $this->replaceVariables($articleMarketingContent->system, $variables);
        $message = $this->replaceVariables($articleMarketingContent->message, $variables);

        $postData = [
            'model' => env('OPEN_AI_DEFAULT_MODEL', 'gpt-4-1106-preview'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $system,
                ],
                [
                    'role' => 'user',
                    'content' => $message,
                ]
            ],
            'stream' => true,
        ];

        // Stream the response from OpenAI to the client
        $response = new StreamedResponse(function() use ($postData) {
            $ch = curl_init((env('OPEN_AI_ENDPOINT') . '/chat/completions'));
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . env('OPEN_AI_KEY')
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
                echo $data;
                ob_flush();
                flush();
                return strlen($data);
            });

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);

            curl_exec($ch);

            if (curl_errno($ch)) {
                dd('Curl error: ' . curl_error($ch));
            }

            curl_close($ch);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    /**
     * Replaces variables in a string
     *
     * @param string $string
     * @param array $variables
     * @return array|string|string[]
     */
    private function replaceVariables(string $string, array $variables)
    {
        foreach ($variables as $key => $value) {
            $string = str_replace('{' . $key . '}', $value, $string);
        }

        return $string;
    }
}
