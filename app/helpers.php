<?php

use Illuminate\Support\Facades\Schema;

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
