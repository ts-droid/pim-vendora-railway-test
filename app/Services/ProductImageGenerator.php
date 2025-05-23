<?php

namespace App\Services;

use App\Services\AI\OpenAIService;

class ProductImageGenerator
{
    const PROMPT_MODEL = 'gpt-4o';
    const IMAGE_MODEl = 'gpt-4o';

    public function generateLifestyleImage(string $productDescription, string $productImageURL): ?string
    {
        $prompt = $this->generateImagePrompt($productDescription);

        if (!$prompt) return null;

        $imageMime = $this->getImageMimeTypeByExtension($productImageURL);

        $imageBase64 = base64_encode(file_get_contents($productImageURL));
        $imageBase64 = 'data:' . $imageMime . ';base64,' . $imageBase64;

        $openAiService = new OpenAIService(self::IMAGE_MODEl);
        $response = $openAiService->generateImage($prompt, $imageBase64);

        $outputImageBase64 = null;

        if (isset($response['output'])) {
            foreach ($response['output'] as $output) {
                if ($output['type'] == 'image_generation_call') {
                    $outputImageBase64 = 'data:' . $imageMime . ';base64,' . $output['result'];
                    break;
                }
            }
        }

        return $outputImageBase64;
    }

    private function generateImagePrompt(string $productDescription): string
    {
        $system = 'You are provided with a product description. Your task is to make a new prompt that generates a photorealistic photo shoot image for the product.

        The product should be placed in an environment that is relevant for the product in order to demonstrate the product in a real life environment.

        Respond only with the image prompt and no other data or text.';

        $message = 'Product description: ' . $productDescription;

        $openAiService = new OpenAIService(self::PROMPT_MODEL);

        return $openAiService->chatCompletion($system, $message);
    }

    private function getImageMimeTypeByExtension(string $path)
    {
        $mimeTypes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'bmp'  => 'image/bmp',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            'tiff' => 'image/tiff',
            'ico'  => 'image/vnd.microsoft.icon',
        ];

        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION));

        return $mimeTypes[$extension] ?? 'image/jpeg';
    }
}
