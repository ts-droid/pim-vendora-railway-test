<?php

namespace App\Http\Controllers;

use App\Services\AI\AIService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIController extends Controller
{
    private $buffer = '';

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
                        $this->buffer .= $data;
                        $this->processBuffer();
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

    private function processBuffer()
    {
        $lines = explode("\n", $this->buffer);
        $this->buffer = '';
        $jsonBuffer = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (str_starts_with($line, 'data: ')) {
                $jsonData = substr($line, 6); // Remove 'data: ' prefix
                $jsonBuffer .= $jsonData;

                // Check if we have a complete JSON object
                $decoded = json_decode($jsonBuffer, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->outputChunk($jsonBuffer . "\n");
                    $jsonBuffer = '';
                }
            } elseif ($line === 'data: [DONE]') {
                $this->outputChunk("[DONE]\n");
                break;
            } else {
                // If it's not a data line, add it back to the buffer
                $this->buffer .= $line . "\n";
            }
        }

        // Add any remaining incomplete JSON data back to the buffer
        $this->buffer .= $jsonBuffer;
    }

    private function outputChunk($data)
    {
        echo $data;
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }
}
