<?php

use Illuminate\Support\Facades\Schema;

if (!function_exists('log_data')) {
    function log_data(string $logContent)
    {
        \App\Models\Log::create(['log_content' => $logContent,]);
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
