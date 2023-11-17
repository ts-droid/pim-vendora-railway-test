<?php

use Illuminate\Support\Facades\Schema;

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

        return $attributes;
    }
}
