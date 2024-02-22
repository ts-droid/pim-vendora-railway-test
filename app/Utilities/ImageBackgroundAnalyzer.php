<?php

namespace App\Utilities;

class ImageBackgroundAnalyzer
{
    /**
     * Returns true of the image has a solid background
     * @param string $content
     * @param int $sideBarWidth With in pixels of the top and left control bars
     * @param float $sidebarLength The length in percent of the top and left control bars
     * @return bool
     */
    public static function hasSolidBackgroundAdvanced(string $content, int $sideBarWidth = 10, float $sidebarLength = 0.6): bool
    {
        $image = @imagecreatefromstring($content);
        if (!$image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $totalPixels = $width * $height;

        $colorCounts = [];
        $colorsCountCorner = [];

        // Iterate over each pixel in the image
        for ($x = 0;$x < $width;$x++) {
            for ($y = 0;$y < $height;$y++) {
                // Get the color index for the pixel
                $rgb = imagecolorat($image, $x, $y);

                // Get the alpha value
                $alpha = ($rgb & 0x7F000000) >> 24;

                if ($alpha > 0) {
                    // Transparent pixel found, this must be a solid background image
                    return true;
                }

                // Convert the color index to a hex code
                $colors = imagecolorsforindex($image, $rgb);
                $hexCode = sprintf("#%02x%02x%02x", $colors['red'], $colors['green'], $colors['blue']);

                // Increment the count for this hex code
                if (!isset($colorCounts[$hexCode])) {
                    $colorCounts[$hexCode] = 1;
                } else {
                    $colorCounts[$hexCode]++;
                }

                if (($x <= $sideBarWidth && $y < ($height * $sidebarLength))
                    || ($y <= $sideBarWidth && $x < ($width * $sidebarLength))) {
                    // This is a top row or left side pixel
                    if (!isset($colorsCountCorner[$hexCode])) {
                        $colorsCountCorner[$hexCode] = 1;
                    } else {
                        $colorsCountCorner[$hexCode]++;
                    }
                }
            }
        }

        // Calculate percentages
        $colorPercentages = [];
        foreach ($colorCounts as $hexCode => $count) {
            $colorPercentages[$hexCode] = ($count / $totalPixels) * 100;
        }

        $cornerPercentages = [];
        $totalCornerPixels = array_sum($colorsCountCorner);

        foreach ($colorsCountCorner as $hexCode => $count) {
            $cornerPercentages[$hexCode] = ($count / $totalCornerPixels) * 100;
        }

        // Free up memory
        imagedestroy($image);

        $nearlyWhiteColors = self::getNearlyWhiteColors($colorPercentages);
        $nearlyWhitePercentage = array_sum($nearlyWhiteColors);

        $nearlyWhiteCornerColors = self::getNearlyWhiteColors($cornerPercentages, 15);
        $nearlyWhiteCornerColorsPercentage = array_sum($nearlyWhiteCornerColors);

        if ($nearlyWhiteCornerColorsPercentage < 98) {
            // Not all corner pixels are white
            return false;
        }

        if ($nearlyWhitePercentage < 20) {
            // Under 20% of the image is not white, this can not be a solid image
            return false;
        }

        return true;
    }

    /**
     * Checks if an image has a solid background. (Solid = All pixels are the same color)
     * By default it checks the corners of the image
     *
     * @param string $content
     * @param $checkType
     * @return bool
     */
    public static function hasSolidBackground(string $content, $checkType = 'corners'): bool
    {
        $image = @imagecreatefromstring($content);

        if (!$image) {
            return false;
        }

        // Get image dimensions
        $width = imagesx($image);
        $height = imagesy($image);

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
     * Returns an array of nearly white colors
     *
     * @param array $colorPercentages
     * @param int $offsetPercent
     * @return array
     */
    private static function getNearlyWhiteColors($colorPercentages, $offsetPercent = 10) {
        $nearlyWhiteHexCodes = [];

        foreach ($colorPercentages as $hexCode => $percentage) {
            // Strip the leading '#' and convert hex to RGB
            $rgb = sscanf($hexCode, "#%02x%02x%02x");

            // Check if each color component is >= 229 (90% of 255)
            $threshold = round(255 - (255 * ($offsetPercent / 100)));

            if ($rgb[0] >= $threshold && $rgb[1] >= $threshold && $rgb[2] >= $threshold) {
                // Optionally, you can adjust this condition to check the percentage of the image
                // the color covers if you also want to filter on that.
                $nearlyWhiteHexCodes[$hexCode] = $percentage;
            }
        }

        return $nearlyWhiteHexCodes;
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
