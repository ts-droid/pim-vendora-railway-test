<?php

namespace App\Http\Controllers;

use App\Services\AI\AIService;
use App\Services\ProductImageGenerator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIController extends Controller
{
    public function stream(Request $request): StreamedResponse
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        set_time_limit(0);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $model = (string)$request->input('model', '');
        $system = (string)$request->input('system', '');
        $message = (string)$request->input('message', '');
        $imageURL = (string)$request->input('image_url', '');

        $aiService = new AIService($model);
        $streamData = $aiService->streamChatCompletion($system, $message, $imageURL);

        $headers = [];
        foreach ($streamData['headers'] as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        $streamData['headers'] = $headers;

        $response = new StreamedResponse(function () use ($streamData) {
            $type = $streamData['type'];

            $ch = curl_init($streamData['url']);

            curl_setopt($ch, CURLOPT_HTTPHEADER, $streamData['headers']);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($streamData['body']));
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($type) {
                switch ($type) {
                    case 'openai':
                    case 'perplexity':
                    case 'deepseek':
                        echo $data;
                        if (ob_get_length() !== false) {
                            ob_flush();
                            flush();
                        }
                        break;

                    case 'claude':
                        $lines = explode("\n", $data);
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (empty($line)) continue;

                            if (str_starts_with($line, 'data: ')) {
                                $jsonData = substr($line, 6); // Remove 'data: ' prefix
                                echo "data: $jsonData\n\n";  // Format as SSE
                                if (ob_get_length() !== false) {
                                    ob_flush();
                                    flush();
                                }
                            }
                        }
                        break;
                }

                return strlen($data);
            });

            curl_exec($ch);

            if (curl_errno($ch)) {
                throw new \Exception('Curl error: ' . curl_error($ch));
            }

            curl_close($ch);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }

    public function generateLifestyleImage(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $productDescription = (string) $request->input('product_description', '');
        $imageUrl = (string) $request->input('image_url', '');
        $descriptionPrompt = (string) $request->input('description_prompt', '');
        $settingPrompt = (string) $request->input('setting_prompt', '');
        $generationPrompt = (string) $request->input('generation_prompt', '');

        if (!$productDescription || !$imageUrl || !$generationPrompt) {
            return ApiResponseController::error('Missing required parameter "product_description", "image_url" and/or "generation_prompt".');
        }

        try {
            $productImageGenerator = new ProductImageGenerator();

            if ($descriptionPrompt && $settingPrompt && $generationPrompt) {
                $imageBase64 = $productImageGenerator->generateLifestyleImage($productDescription, $imageUrl, $descriptionPrompt, $settingPrompt, $generationPrompt);
            } else {
                $imageBase64 = $productImageGenerator->generateLifestyleImageQuick($productDescription, $imageUrl, $generationPrompt);
            }
        } catch (\Throwable $e) {
            return ApiResponseController::error($e->getMessage());
        }

        if (!$imageBase64) {
            return ApiResponseController::error('Failed to generate image. Please try again.');
        }

        return ApiResponseController::success([
            'image_base_64' => $imageBase64
        ]);
    }
}
