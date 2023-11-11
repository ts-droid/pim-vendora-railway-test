<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class DoSpacesController extends Controller
{
    /**
     * Creates a new file with a unique filename
     *
     * @param string $filename
     * @param string $content
     * @param bool $isPublic
     * @return string
     */
    public static function store(string $filename, string $content, bool $isPublic = false): string
    {
        $folder = config('filesystems.disks.do.folder');

        $filename = self::getUniqueFilename($filename);

        $path = $folder . '/' . $filename;

        Storage::disk('do')->put(
            $path,
            $content,
            ($isPublic ? 'public' : 'private')
        );

        return $filename;
    }

    /**
     * Updates a file (creates a new if not existing)
     *
     * @param string $filename
     * @param string $content
     * @param bool $isPublic
     */
    public static function update(string $filename, string $content, bool $isPublic = false): void
    {
        $folder = config('filesystems.disks.do.folder');

        $path = $folder . '/' . $filename;

        Storage::disk('do')->put(
            $path,
            $content,
            ($isPublic ? 'public' : 'private')
        );
    }

    /**
     * Return file content
     *
     * @param string $filename
     * @return string|null
     */
    public static function getContent(string $filename): ?string
    {
        return Storage::disk('do')->get($filename);
    }

    /**
     * Returns a public URL
     *
     * @param string $filename
     * @return string
     */
    public static function getURL(string $filename): string
    {
        return Storage::disk('do')->url($filename);
    }

    /**
     * Returns a signed URL
     *
     * @param string $filename
     * @return string
     */
    public static function getSignedURL(string $filename): string
    {
        $expiration = Carbon::now()->addMinutes(60);

        return Storage::disk('do')->temporaryUrl($filename, $expiration);
    }

    /**
     * Make a file public
     *
     * @param string $filename
     * @return void
     */
    public static function setPublic(string $filename): void
    {
        Storage::disk('do')->setVisibility($filename, 'public');
    }

    /**
     * Make a file private
     *
     * @param string $filename
     * @return void
     */
    public static function setPrivate(string $filename): void
    {
        Storage::disk('do')->setVisibility($filename, 'private');
    }

    /**
     * Returns the file size
     *
     * @param string $filename
     * @return int
     */
    public static function getSize(string $filename): int
    {
        return Storage::disk('do')->size($filename);
    }

    /**
     * Deletes a file
     *
     * @param string $filename
     */
    public static function delete(string $filename): void
    {
        $folder = config('filesystems.disks.do.folder');

        $path = $folder . '/' . $filename;

        Storage::disk('do')->delete($path);
    }

    /**
     * Uploads all local files to the storage bucket. (Skips already existing files)
     *
     * @return void
     */
    public static function storeLocalFiles(): void
    {
        $filePaths = self::listFilesRecursively(storage_path('app'));

        foreach ($filePaths as $filePath) {

            // Skip .txt files and .gitignore files
            if (str_ends_with($filePath, '.txt')
                || str_ends_with($filePath, '.gitignore')) {
                continue;
            }

            // Store the file in the storage bucket
            self::store(
                basename($filePath),
                file_get_contents($filePath)
            );

        }
    }

    /**
     * Returns a unique filename
     *
     * @param string $filename
     * @return string
     */
    private static function getUniqueFilename(string $filename): string
    {
        $folder = config('filesystems.disks.do.folder');
        $basename = basename($filename);

        $i = 0;

        while(true) {
            $newBasename = ($i ? '' : ($i . '-')) . $basename;

            $newFilename = str_replace($basename, $newBasename, $filename);

            $path = $folder . '/' . $newFilename;

            $content = Storage::disk('do')->get($path);

            if (!$content) {
                return $newFilename;
            }

            $i++;
        }
    }

    /**
     * Returns the path to all files within a directory recursively
     *
     * @param string $dir
     * @return array
     */
    private static function listFilesRecursively(string $dir): array
    {
        $result = [];

        // Scan the directory and filter out '.', '..'
        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            // Full path of the current file
            $filePath = $dir . '/' . $file;

            // Check if it's a directory
            if (is_dir($filePath)) {
                // Recursively get files from the subdirectory
                $result = array_merge($result, self::listFilesRecursively($filePath));
            }
            else {
                // Add files to result
                $result[] = $filePath;
            }
        }

        return $result;
    }
}
