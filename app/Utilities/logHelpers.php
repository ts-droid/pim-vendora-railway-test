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

        if (should_skip_action_log($message, $context)) {
            return;
        }

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

if (!function_exists('should_skip_action_log')) {
    function should_skip_action_log(string $message, array $context): bool
    {
        $method = $context['action'] ?? $context['method'] ?? null;

        if (isset($context['controller']) && is_read_only_method_name($method)) {
            if (
                str_contains($message, 'Invoked controller') ||
                str_contains($message, 'Handling controller action.')
            ) {
                return true;
            }
        }

        if (
            isset($context['service']) &&
            str_contains($message, 'Invoked service') &&
            is_read_only_method_name($method)
        ) {
            return true;
        }

        if (
            isset($context['utility']) &&
            str_contains($message, 'Invoked utility') &&
            is_read_only_method_name($method)
        ) {
            return true;
        }

        return false;
    }
}

if (!function_exists('is_read_only_method_name')) {
    function is_read_only_method_name(?string $method): bool
    {
        if (!$method) {
            return false;
        }

        $method = Str::snake($method);
        $tokens = [
            'get',
            'fetch',
            'list',
            'show',
            'index',
            'search',
            'count',
            'find',
            'load',
            'view',
            'display',
            'preview',
            'check',
            'status',
            'history',
            'report',
        ];

        foreach ($tokens as $token) {
            if (
                Str::startsWith($method, $token) ||
                Str::endsWith($method, $token) ||
                Str::contains($method, '_' . $token . '_')
            ) {
                return true;
            }
        }

        return false;
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
            $limit = (int) config('logging.action_log_max_keys', 10);
            $limit = $limit > 0 ? $limit : 0;
            $truncatedCount = 0;

            if ($limit > 0 && count($value) > $limit) {
                $truncatedCount = count($value) - $limit;
                $value = array_slice($value, 0, $limit, true);
            }

            foreach ($value as $key => $item) {
                $value[$key] = sanitize_action_log_value($item);
            }

            if ($truncatedCount > 0) {
                $value['__truncated__'] = "... (truncated {$truncatedCount} keys)";
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
