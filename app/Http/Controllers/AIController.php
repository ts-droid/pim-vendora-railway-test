<?php

namespace App\Http\Controllers;

use App\Services\AI\AIService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIController extends Controller
{
    public function stream(Request $request): StreamedResponse
    {
        set_time_limit(0);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $model = (string)$request->input('model', '');
        $system = (string)$request->input('system', '');
        $message = (string)$request->input('message', '');

        $aiService = new AIService($model);
        $streamData = $aiService->streamChatCompletion($system, $message);

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
                            if ($line === '') continue;

                            if (str_starts_with($line, 'data: ')) {
                                $jsonData = trim(substr($line, 6)); // Remove 'data: ' prefix and trim
                                $decoded = json_decode($jsonData, true);

                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $text = $decoded['delta']['text'] ?? '';

                                    echo $text;
                                    if (ob_get_level() > 0) {
                                        ob_end_flush();
                                    }
                                    flush();
                                    if (function_exists('fastcgi_finish_request')) {
                                        fastcgi_finish_request();
                                    }
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
}
