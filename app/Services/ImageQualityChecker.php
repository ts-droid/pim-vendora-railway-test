<?php

namespace App\Services;

use Imagick;

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

    public function __construct(float $blurThreshold = 4000.0, int $minResolution = 50000)
    {
        $this->blurThreshold = $blurThreshold;
        $this->minResolution = $minResolution;
    }

    /**
     * Check if the image is blurry.
     *
     * @param string $imagePath
     * @return bool
     */
    public function isBlurry(string $imagePath): bool
    {
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

    /**
     * Check if the image is of good quality.
     * Combines blur and resolution checks.
     *
     * @param string $imagePath
     * @return bool
     */
    public function isGoodQuality(string $imagePath): bool
    {
        return !$this->isBlurry($imagePath) && $this->isHighResolution($imagePath);
    }

    /**
     * Optionally, allow setting a custom blur threshold.
     *
     * @param float $threshold
     * @return void
     */
    public function setBlurThreshold(float $threshold): void
    {
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
        $this->minResolution = $resolution;
    }
}
