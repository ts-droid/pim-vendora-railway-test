<?php

use Illuminate\Support\Facades\Log;

if (!function_exists('action_log')) {
    function action_log(string $message, array $context = [], string $level = 'info'): void
    {
        $context = array_merge([
            'server' => env('LOG_SERVER_NAME') ?: gethostname(),
        ], $context);

        Log::channel('actions')->{$level}($message, $context);
    }
}
