<?php

namespace App\Services;

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

            if (Storage::disk('public')->exists($filename)) {
                return Storage::url($filename);
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

            Storage::disk('public')->put($filename, $flattenedPng);

            return Storage::url($filename);
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
