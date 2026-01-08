<?php

namespace App\Services;

use App\Http\Controllers\DoSpacesController;
use App\Services\AI\OpenAIService;
use Imagick;
use thiagoalessio\TesseractOCR\TesseractOCR;
use thiagoalessio\TesseractOCR\TesseractOcrException;

class ImageQualityChecker
{
    /**
     * Threshold for determining blurriness.
     * Images with a variance below this threshold are considered blurry.
     * Adjust this value based on your requirements.
     *
     * @var float
     */
    protected float $blurThreshold;

    /**
     * Minimum required resolution (width * height).
     * Images below this resolution are considered low quality.
     *
     * @var int
     */
    protected int $minResolution;

    /**
     * Threshold for determining image sharpness.
     * Images with a sharpness below this threshold are considered low quality.
     * Adjust this value based on your requirements (0 - 100 where 0 is the worst and 100 is the best).
     *
     * @var int
     */
    protected int $sharpnessThreshold;

    public function __construct(float $blurThreshold = 4000.0, int $minResolution = 50000, int $sharpnessThreshold = 70)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $this->blurThreshold = $blurThreshold;
        $this->minResolution = $minResolution;
        $this->sharpnessThreshold = $sharpnessThreshold;
    }

    /**
     * Check if the image is blurry.
     *
     * @param string $imagePath
     * @return bool
     */
    public function isBlurry(string $imagePath): bool
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        try {
            $imagick = new Imagick($imagePath);

            // Convert to grayscale
            $imagick->transformImageColorspace(Imagick::COLORSPACE_GRAY);

            // Apply Laplacian filter
            $imagick->edgeImage(1);

            // Get image statistics
            $statistics = $imagick->getImageChannelStatistics();

            $grayChannel = Imagick::CHANNEL_GRAY;

            if (!isset($statistics[$grayChannel])) {
                // If the grayscale channel is not present, consider the image as blurry
                $imagick->clear();
                $imagick->destroy();
                return true;
            }

            // Retrieve the standard deviation
            if (!isset($statistics[$grayChannel]['standardDeviation'])) {
                // If 'standardDeviation' is not set, consider the image as blurry
                $imagick->clear();
                $imagick->destroy();
                return true;
            }

            $standardDeviation = $statistics[$grayChannel]['standardDeviation'];
            $variance = pow($standardDeviation, 2);

            $imagick->clear();
            $imagick->destroy();

            return $standardDeviation < $this->blurThreshold;

        } catch (\ImagickException $e) {
            // For now, we'll consider the image as bad quality if an error occurs
            return true;
        }
    }

    /**
     * Check if the image meets minimum resolution requirements.
     *
     * @param string $imagePath
     * @return bool
     */
    public function isHighResolution(string $imagePath): bool
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        try {
            $imagick = new Imagick($imagePath);
            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();

            $imagick->clear();
            $imagick->destroy();

            return ($width * $height) >= $this->minResolution;
        } catch (\ImagickException $e) {
            // For now, we'll consider the image as low resolution if an error occurs
            return false;
        }
    }

    public function isSharp(string $imagePath): int
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        // Upload the image to storage and get the URL
        $imageContent = @file_get_contents($imagePath);
        if ($imageContent === false) {
            return -1;
        }

        $imageExtension = pathinfo($imagePath, PATHINFO_EXTENSION);
        $filename = 'tmp/' . uniqid() . '.' . $imageExtension;

        $remoteFilename = DoSpacesController::store($filename, $imageContent, true);
        $imageURL = DoSpacesController::getURL($remoteFilename);

        if (!$imageURL) {
            return -1;
        }

        $model = 'gpt-4o-2024-08-06';

        $body = [
            'model' => $model,
            'max_tokens' => 4095,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Return only JSON without any Markdown formatting or additional text.',
                        ]
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Analyze the attached image and respond with a JSON object with the following keys
                                `image_quality` = A integer indicating the quality/sharpness of the image between 0 and 100 where 0 is the worst and 100 is the best.
                            ",
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $imageURL,
                            ],
                        ]
                    ],
                ],
            ],
        ];

        try {
            $openAIService = new OpenAIService($model);
            $response = $openAIService->callAPI('POST', '/chat/completions', $body);

            $message = $response['choices'][0]['message']['content'] ?? '';

            $array = json_decode($message, true);
        } catch (\Exception $e) {
            $array = [];
        }

        // Remove the tmp file
        DoSpacesController::delete($filename);

        return ($array['image_quality'] ?? -1) >= $this->sharpnessThreshold;
    }

    /**
     * Check if the image is of good quality.
     * Combines blur and resolution checks.
     *
     * @param string $imagePath
     * @return bool
     */
    public function isGoodQuality(string $imagePath): bool
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        return (!$this->isBlurry($imagePath)
            && $this->isHighResolution($imagePath)
            && $this->isSharp($imagePath));
    }

    /**
     * Optionally, allow setting a custom blur threshold.
     *
     * @param float $threshold
     * @return void
     */
    public function setBlurThreshold(float $threshold): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $this->blurThreshold = $threshold;
    }

    /**
     * Optionally, allow setting a custom minimum resolution.
     *
     * @param int $resolution
     * @return void
     */
    public function setMinResolution(int $resolution): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $this->minResolution = $resolution;
    }
}
