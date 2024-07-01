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

        while(ob_get_level() > 0) {
            ob_end_clean();
        }

        $model = (string) $request->input('model', '');
        $system = (string) $request->input('system', '');
        $message = (string) $request->input('message', '');

        $aiService = new AIService($model);
        $streamData = $aiService->streamChatCompletion($system, $message);

        $response = new StreamedResponse(function() use ($streamData) {
            $ch = curl_init($streamData['url']);

            curl_setopt($ch, CURLOPT_HTTPHEADER, $streamData['headers']);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($streamData['body']));
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
                echo $data;
                if (ob_get_length() !== false) {
                    ob_flush();
                    flush();
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
