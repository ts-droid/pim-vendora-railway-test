<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Intl\Countries;

if(!function_exists('get_fixed_postal_code'))
{
    function get_fixed_postal_code(string $postalCode, string $countryCode): string
    {
        if (in_array($postalCode, ['216 17'])) {
            return $postalCode;
        }

        switch ($countryCode) {
            case 'GB':
                // A space is required so we cannot remove all whitespace
                return trim($postalCode);

            case 'SE':
                // Only digits
                return preg_replace('/\D/', '', $postalCode);

            default:
                // Remove whitespace
                return preg_replace('/\s/', '', $postalCode);
        }
    }
}

if (!function_exists('clean_string_for_comparison'))
{
    function clean_string_for_comparison(mixed $string): string
    {
        $string = (string) $string;

        $cleanString = html_entity_decode($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleanString = strip_tags($cleanString);
        $cleanString = str_replace("\xC2\xA0", '', $cleanString);
        return preg_replace('/\s+/u', '', $cleanString);
    }
}

if (!function_exists('get_google_product_categories')) {
    function get_google_product_categories()
    {
        $categories = [];

        $filename = storage_path('google_product_categories.txt');

        $file = fopen($filename, 'r');
        while (($line = fgets($file)) !== false) {
            list($id, $category) = explode(' - ', $line);
            $categories[$id] = trim($category);
        }

        return $categories;
    }
}

if (!function_exists('get_internal_api_key')) {
    function get_internal_api_key()
    {
        $key = DB::table('api_keys')->whereNull('expires_at')->first();
        return $key->api_key ?? '';
    }
}

if (!function_exists('has_hours_passed')) {
    function has_hours_passed(string $timestamp, int $hours): bool
    {
        $start = \Carbon\Carbon::parse($timestamp);
        $now = \Carbon\Carbon::now();

        $target = $start->copy();
        $remainingHours = $hours;

        while ($remainingHours > 0) {
            $target->addHour();
            if (!$target->isWeekend()) {
                $remainingHours--;
            }
        }

        return $now->greaterThan($target);
    }
}

if (!function_exists('is_eu_country')) {
    function is_eu_country(string $countryCode)
    {
        $euCountries = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR',
            'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK',
            'SI', 'ES', 'SE', 'GB'
        ];

        return in_array(strtoupper($countryCode), $euCountries);
    }
}

if (!function_exists('add_vat')) {
    function add_vat(float $amount, float|int $vatRate)
    {
        return round($amount * (1 + ($vatRate / 100)), 2);
    }
}

if (!function_exists('is_web_customer')) {
    function is_web_customer(string $customerNumber): ?bool
    {
        return true;

        return in_array($customerNumber, ['10460', '10461', '10462', '10463', '10464']);
    }
}

if (!function_exists('get_country_name')) {
    function get_country_name(string $countryCode, string $languageCode = 'en')
    {
        if (!$countryCode || !$languageCode) {
            return '';
        }

        return Symfony\Component\Intl\Countries::getName($countryCode, $languageCode);
    }
}

if (!function_exists('get_image_base_64')) {
    function get_image_base_64(string $path): ?string
    {
        $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;

        // Handle URL
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            try {
                $data = file_get_contents($path);
                if ($data === false || $data === '') {
                    return null;
                }

                $mimeType = $finfo ? $finfo->buffer($data) : null;
                return build_image_data_uri($data, $mimeType, $path);
            } catch (\Exception $e) {
                return null;
            }
        }

        // Handle local file
        if (!file_exists($path)) {
            $publicPath = public_path($path);
            if (!file_exists($publicPath)) {
                return null;
            }
            $path = $publicPath;
        }

        $data = @file_get_contents($path);

        if ($data === false || $data === '') {
            return null;
        }

        $mimeType = $finfo ? $finfo->buffer($data) : null;

        return build_image_data_uri($data, $mimeType, $path);
    }
}

if (!function_exists('build_image_data_uri')) {
    function build_image_data_uri(string $data, ?string $mimeType, string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $extensionMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];
        $resolvedMime = $mimeType ?: ($extensionMap[$extension] ?? ($extension ? 'image/' . $extension : null));

        if (!$resolvedMime || strpos($resolvedMime, 'image/') !== 0) {
            return null;
        }

        if ($resolvedMime === 'image/svg+xml') {
            if (stripos($data, '<svg') === false) {
                return null;
            }

            return 'data:' . $resolvedMime . ';base64,' . base64_encode($data);
        }

        if (!@getimagesizefromstring($data)) {
            return null;
        }

        $image = @imagecreatefromstring($data);
        if (!$image) {
            return null;
        }

        if (in_array($resolvedMime, ['image/png', 'image/gif', 'image/webp'], true)) {
            $width = imagesx($image);
            $height = imagesy($image);
            if (!$width || !$height) {
                imagedestroy($image);
                return null;
            }

            $canvas = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
            imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

            ob_start();
            imagejpeg($canvas, null, 90);
            $jpegData = ob_get_clean();

            imagedestroy($canvas);
            imagedestroy($image);

            if (!$jpegData) {
                return null;
            }

            return 'data:image/jpeg;base64,' . base64_encode($jpegData);
        }

        imagedestroy($image);

        return 'data:' . $resolvedMime . ';base64,' . base64_encode($data);
    }
}

if (!function_exists('get_article_image')) {
    function get_article_image(string $articleNumber)
    {
        $articleID = DB::table('articles')
            ->where('article_number', $articleNumber)
            ->value('id');

        if (!$articleID) {
            return null;
        }

        $articleImage = DB::table('article_images')
            ->select('path_url')
            ->where('article_id', $articleID)
            ->orderBy('list_order', 'ASC')
            ->first();

        return $articleImage->path_url ?: null;
    }
}

if (!function_exists('trigger_stock_sync')) {
    function trigger_stock_sync(string $articleNumber)
    {
        DB::table('articles')
            ->where('article_number', $articleNumber)
            ->update(['stock_sync' => 1]);
    }
}

if (!function_exists('clear_stock_sync')) {
    function clear_stock_sync(string $articleNumber)
    {
        DB::table('articles')
            ->where('article_number', $articleNumber)
            ->update(['stock_sync' => 0]);
    }
}

if (!function_exists('should_sync_stock')) {
    function should_sync_stock(string $articleNumber)
    {
        // Never sync stock for a article with unmanaged stock marked for investigation
        /*$hasInvestigation = DB::table('stock_keep_transactions')
            ->where('status', '=', 'investigation')
            ->where('article_number', '=', $articleNumber)
            ->where('identifiers', 'LIKE', '%--%')
            ->exists();

        if ($hasInvestigation) {
            return false;
        }*/

        // Check if the article is set to sync stock
        return (bool) DB::table('articles')
            ->select('stock_sync')
            ->where('article_number', $articleNumber)
            ->value('stock_sync');
    }
}

if (!function_exists('get_display_name')) {
    function get_display_name(): string
    {
        $displayName = (string) request()->header('display-name', '');

        $displayName = str_replace('å', 'a', $displayName);
        $displayName = str_replace('ä', 'a', $displayName);
        $displayName = str_replace('ö', 'o', $displayName);
        $displayName = str_replace('Å', 'A', $displayName);
        $displayName = str_replace('Ä', 'A', $displayName);
        $displayName = str_replace('Ö', 'O', $displayName);

        return $displayName;
    }
}

if (!function_exists('is_wgr_active')) {
    function is_wgr_active(): bool
    {
        return (bool) \App\Http\Controllers\ConfigController::getConfig('wgr_is_active');
    }
}

if (!function_exists('makeFilenameFriendly')) {
    function makeFilenameFriendly($string): string
    {
        // Convert the string to lowercase
        $string = strtolower($string);

        // Replace spaces with underscores
        $string = str_replace(' ', '_', $string);

        // Remove any character that is not alphanumeric, a dash, or an underscore
        $string = preg_replace('/[^a-z0-9_-]/', '', $string);

        // Trim any leading or trailing underscores
        $string = trim($string, '_');

        return $string;
    }
}

if (!function_exists('translation_service')) {
    function translation_service()
    {
        $service = \Illuminate\Support\Facades\Cache::store('array')->get('translation_service', null);
        if (!$service) {
            return '';
        }

        return $service->id;
    }
}

if (!function_exists('get_user_ip')) {
    function get_user_ip()
    {
        // Cloudflare
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        // Laravel Forge Load Balancer
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddresses = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ipAddresses[0]);
        }

        // Other Proxies
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        }

        if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
            return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        }

        if (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_FORWARDED_FOR'];
        }

        if (!empty($_SERVER['HTTP_FORWARDED'])) {
            return $_SERVER['HTTP_FORWARDED'];
        }

        // Default
        return request()->ip();
    }
}

if (!function_exists('default_ai_model')) {
    function default_ai_model()
    {
        return \App\Http\Controllers\ConfigController::getConfig('openai_default_model');
    }
}

if (!function_exists('remove_quotations')) {
    function remove_quotations(string $string)
    {
        $string = trim($string);

        return trim($string, '"');
    }
}

if (!function_exists('normalize_array')) {
    function weight_array(array $array)
    {
        $max = max($array);
        $total = min($array);

        $normalizedArray = array_map(function ($value) use ($total, $max) {
            if ($total == 0) {
                return 0;
            }

            $relativeWeight = $value / $total;
            $scaledWeight = $relativeWeight / ($max / $total);
            return min($scaledWeight, 1);
        }, $array);

        return $normalizedArray;
    }
}

if (!function_exists('log_data')) {
    function log_data(string $logContent)
    {
        $log = \App\Models\Log::create(['log_content' => $logContent]);
        return $log->id;
    }
}

if (!function_exists('get_model_attributes')) {
    function get_model_attributes($model)
    {
        $attributes = (new $model)->getFillable();
        if (!$attributes) {
            $attributes = Schema::getColumnListing((new $model)->getTable());
        }

        $attributes ?: [];

        if (!in_array('id', $attributes)) {
            $attributes[] = 'id';
        }

        // Remove $appends from the attributes
        $appends = (new $model)->getAppends();
        $attributes = array_diff($attributes, $appends);

        return $attributes;
    }
}
