<?php

namespace App\Services;

use App\Http\Controllers\DoSpacesController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmailImageService
{
    protected static string $directory = 'email-images';

    public static function prepareImageForEmail(string $url): ?string
    {
        if (App::environment('local')) {
            return $url;
        }

        try {
            $hash = md5($url);
            $filename = self::$directory . '/' . $hash . '.png';

            if (DoSpacesController::getContent($filename)) {
                return DoSpacesController::getURL($filename);
            }

            $response = Http::timeout(10)->get($url);
            if (!$response->successful()) {
                return null;
            }

            $imageData = $response->body();
            $srcImage = @imagecreatefromstring($imageData);
            if (!$srcImage) {
                return null;
            }

            $width = imagesx($srcImage);
            $height = imagesy($srcImage);

            $dstImage = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($dstImage, 255, 255, 255);
            imagefilledrectangle($dstImage, 0, 0, $width, $height, $white);
            imagecopy($dstImage, $srcImage, 0, 0, 0, 0, $width, $height);

            ob_start();
            imagepng($dstImage);
            $flattenedPng = ob_get_clean();

            imagedestroy($srcImage);
            imagedestroy($dstImage);

            DoSpacesController::store($filename, $flattenedPng, true);

            return DoSpacesController::getURL($filename);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function prepareBase64ImageForEmail(string $base64): ?string
    {
        if (App::environment('local')) {
            return $base64;
        }

        try {
            $data = explode(',', $base64, 2);
            if (count($data) !== 2) return null;

            $imageData = base64_decode($data[1]);
            if ($imageData === false) return null;

            $hash = md5($imageData);
            $filename = self::$directory . '/' . $hash . '.png';

            if (DoSpacesController::getContent($filename)) {
                return DoSpacesController::getURL($filename);
            }

            $imagick = new \Imagick();
            $imagick->readImageBlob($imageData);
            $imagick->setImageFormat('png');

            $flattenedPng = $imagick->getImageBlob();
            $imagick->destroy();

            DoSpacesController::store($filename, $flattenedPng, true);

            return DoSpacesController::getURL($filename);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function cleanupOldImages(int $olderThanMinutes = 1440): void
    {
        $files = Storage::disk('public')->files(self::$directory);

        foreach ($files as $file) {
            $path = storage_path('app/public/' . $file);

            if (file_exists($path) && filemtime($path) < now()->subminutes($olderThanMinutes)) {
                Storage::disk('public')->delete($file);
            }
        }
    }
}
