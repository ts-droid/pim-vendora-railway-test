<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!function_exists('is_web_customer')) {
    function is_web_customer(string $customerNumber): ?bool
    {
        return true;

        return in_array($customerNumber, ['10460', '10461', '10462', '10463', '10464']);
    }
}

if (!function_exists('get_country_name')) {
    function get_country_name(string $countryCode, string $languageCode)
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
        // Handle URL
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            try {
                $data = file_get_contents($path);
                $type = pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION);

                if ($data === false) {
                    return null;
                }

                return 'data:image/' . $type . ';base64,' . base64_encode($data);
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

        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = @file_get_contents($path);

        if ($data === false) {
            return null;
        }

        return 'data:image/' . $type . ';base64,' . base64_encode($data);
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
        $hasInvestigation = DB::table('stock_keep_transactions')
            ->where('status', '=', 'investigation')
            ->where('article_number', '=', $articleNumber)
            ->where('identifiers', 'LIKE', '%--%')
            ->exists();

        if ($hasInvestigation) {
            return false;
        }

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
