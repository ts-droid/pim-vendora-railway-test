<?php

namespace App\Jobs\Middleware;

class ArticleSyncControl
{
    public function handle($job, $next)
    {
        action_log('Executing job handle method.', [
            'job' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ]);

        if (is_wgr_active()) {
            $next($job);
        }
        else {
            $job->release(3600);
        }
    }
}
