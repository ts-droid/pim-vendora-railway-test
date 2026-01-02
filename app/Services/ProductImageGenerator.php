<?php

namespace App\Services;

use App\Services\AI\OpenAIService;
use PHPUnit\Event\Runtime\PHP;

class ProductImageGenerator
{
    public function generateLifestyleImage(
        string $productDescription,
        string $productImageURL,
        string $imageDescriptionPrompt = '',
        string $imageSettingsPrompt = '',
        string $imageGenerationPrompt = ''
    ): ?string
    {
        $defaultModel = default_ai_model();
        $openAiService = new OpenAIService($defaultModel);


        // First generate a description of the product image
        $imageDescription = $openAiService->chatCompletion('', $imageDescriptionPrompt, null, $productImageURL);


        // Generate the text that describes how the image should look
        $imageSettingsPrompt .= PHP_EOL . PHP_EOL . 'Product description:' . PHP_EOL . $productDescription;
        $imageSettingsPrompt .= PHP_EOL . PHP_EOL . 'Information about the product image:' . PHP_EOL . $imageDescription;

        $imageSetting = $openAiService->chatCompletion('', $imageSettingsPrompt);


        // Generate the final image
        $imageGenerationPrompt .= PHP_EOL . PHP_EOL . 'Information about the product image: ' . PHP_EOL . $imageDescription;
        $imageGenerationPrompt .= PHP_EOL . PHP_EOL . 'How the image should be created: ' . PHP_EOL . $imageSetting;
        $imageGenerationPrompt .= PHP_EOL . PHP_EOL . 'Product webshop description: ' . PHP_EOL . $productDescription;

        $imageMime = $this->getImageMimeTypeByExtension($productImageURL);

        $imageBase64 = base64_encode(file_get_contents($productImageURL));
        $imageBase64 = 'data:' . $imageMime . ';base64,' . $imageBase64;

        $response = $openAiService->generateImageV2($imageGenerationPrompt, $imageBase64, $imageMime, 'gpt-image-1.5-2025-12-16');

        $outputImageBase64 = null;

        if (!empty($response['data'][0]['b64_json'])) {
            $imageMime = 'image/png';
            $outputImageBase64 = 'data:' . $imageMime . ';base64,' . $response['data'][0]['b64_json'];
        }

        return $outputImageBase64;
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
