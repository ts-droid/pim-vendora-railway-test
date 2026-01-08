<?php

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

if (!defined('ACTION_LOG_VALUE_LIMIT')) {
    define('ACTION_LOG_VALUE_LIMIT', 100);
}

if (!function_exists('action_log')) {
    function action_log(string $message, array $context = [], string $level = 'info'): void
    {
        $context = array_merge([
            'server' => env('LOG_SERVER_NAME') ?: gethostname(),
        ], $context);

        if (
            isset($context['controller'], $context['action']) &&
            !config('logging.controller_actions_enabled', true)
        ) {
            return;
        }

        foreach ($context as $key => $value) {
            $context[$key] = sanitize_action_log_value($value);
        }

        Log::channel('actions')->{$level}($message, $context);
    }
}

if (!function_exists('sanitize_action_log_value')) {
    function sanitize_action_log_value(mixed $value)
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        } elseif ($value instanceof Stringable || (is_object($value) && method_exists($value, '__toString'))) {
            $value = (string) $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = sanitize_action_log_value($item);
            }

            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        if (Str::length($value) <= ACTION_LOG_VALUE_LIMIT) {
            return $value;
        }

        $remaining = Str::length($value) - ACTION_LOG_VALUE_LIMIT;

        return Str::substr($value, 0, ACTION_LOG_VALUE_LIMIT) . "... (truncated {$remaining} chars)";
    }
}
