<?php

namespace App\Utilities;

class ImageBackgroundAnalyzer
{
    /**
     * Checks if an image has a solid background. (Solid = All pixels are the same color)
     * By default it checks the corners of the image
     *
     * @param string $filepath
     * @param $checkType
     * @return bool
     */
    public static function hasSolidBackground(string $filepath, $checkType = 'corners'): bool
    {
        if (!file_exists($filepath)) {
            return false;
        }

        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'png':
                $image = @imagecreatefrompng($filepath);
                break;

            case 'jpg':
            case 'jpeg':
                $image = @imagecreatefromjpeg($filepath);
                break;

            case 'webp':
                $image = @imagecreatefromwebp($filepath);
                break;

            case 'gif':
                $image = @imagecreatefromgif($filepath);
                break;

            default:
                $image = null;
                break;
        }

        if (!$image) {
            return false;
        }

        list($width, $height) = getimagesize($filepath);

        switch ($checkType) {
            case 'topbar':
                $areas = self::getTopBarAreas($width, min(10, $height));
                break;

            case 'corners':
            default:
                $areas = self::getCornerAreas($width, $height, 10);
                break;
        }

        $colors = [];

        foreach ($areas as $area) {
            list($start, $end) = $area;

            for ($x = $start[0];$x < $end[0];$x++) {
                for ($y = $start[1];$y < $end[1];$y++) {

                    $rgb = imagecolorat($image, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;

                    $colors[] = [$r, $g, $b];
                }
            }

        }

        return self::areColorsAlmostSame($colors, 20);
    }

    /**
     * Returns true if all colors are almost the same
     *
     * @param array $colors
     * @param int $tolerance
     * @return bool
     */
    private static function areColorsAlmostSame(array $colors, int $tolerance = 10): bool
    {
        if (count($colors) <= 1) {
            return true;
        }

        $baseColor = $colors[0];

        for ($i = 1; $i < count($colors); $i++) {
            $color = $colors[$i];

            if (abs($baseColor[0] - $color[0]) > $tolerance ||
                abs($baseColor[1] - $color[1]) > $tolerance ||
                abs($baseColor[2] - $color[2]) > $tolerance) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return the corner areas of an image
     *
     * @param int $width
     * @param int $height
     * @param int $size
     * @return array
     */
    private static function getCornerAreas(int $width, int $height, int $size): array
    {
        return [
            [[0, 0], [$size, $size]],
            [[$width - $size, 0], [$width, $size]],
            [[$width - $size, $height - $size], [$width, $height]],
            [[0, $height - $size], [$size, $height]],
        ];
    }

    /**
     * Return the top bar area of an image
     *
     * @param $width
     * @param $height
     * @return array[]
     */
    private static function getTopBarAreas($width, $height): array
    {
        return [
            [[0,0], [$width, $height]]
        ];
    }
}
