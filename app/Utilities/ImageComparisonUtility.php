<?php

namespace App\Utilities;

use Exception;

class ImageComparisonUtility
{
    public static function isBase64ImageSimilar($base64Image1, $base64Image2, $threshold = 100): bool
    {
        $similarity = self::compareBase64Images($base64Image1, $base64Image2);
        return $similarity >= $threshold;
    }

    public static function compareBase64Images($base64Image1, $base64Image2): float
    {
        try {
            $image1 = self::base64ToImage($base64Image1);
            $image2 = self::base64ToImage($base64Image2);

            $resizedImage1 = self::resizeImage($image1, 100, 100);
            $resizedImage2 = self::resizeImage($image2, 100, 100);

            return self::compareImages($resizedImage1, $resizedImage2);
        }
        catch (Exception $e) {
            return 0;
        }
    }

    private static function base64ToImage($base64)
    {
        $imageData = base64_decode($base64);
        $image = imagecreatefromstring($imageData);
        if ($image === false) {
            throw new Exception('Base64 string is not a valid image.');
        }
        return $image;
    }

    private static function resizeImage($image, $newWidth, $newHeight)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        return $newImage;
    }

    private static function colorSimilarity($rgb1, $rgb2): float
    {
        $r1 = ($rgb1 >> 16) & 0xFF;
        $g1 = ($rgb1 >> 8) & 0xFF;
        $b1 = $rgb1 & 0xFF;

        $r2 = ($rgb2 >> 16) & 0xFF;
        $g2 = ($rgb2 >> 8) & 0xFF;
        $b2 = $rgb2 & 0xFF;

        $similarity = (abs($r1 - $r2) / 255 + abs($g1 - $g2) / 255 + abs($b1 - $b2) / 255) / 3;
        return 1 - $similarity;
    }

    private static function compareImages($image1, $image2): float
    {
        $width1 = imagesx($image1);
        $height1 = imagesy($image1);
        $width2 = imagesx($image2);
        $height2 = imagesy($image2);

        if ($width1 !== $width2 || $height1 !== $height2) {
            throw new Exception('Images must be of the same dimensions for comparison.');
        }

        $totalPixels = $width1 * $height1;
        $similarPixels = 0;

        for ($x = 0; $x < $width1; $x++) {
            for ($y = 0; $y < $height1; $y++) {
                $rgb1 = imagecolorat($image1, $x, $y);
                $rgb2 = imagecolorat($image2, $x, $y);

                if (self::colorSimilarity($rgb1, $rgb2) >= 0.8) {
                    $similarPixels++;
                }
            }
        }

        $similarity = ($similarPixels / $totalPixels) * 100;
        return $similarity;
    }
}
