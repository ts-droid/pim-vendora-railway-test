<?php

namespace App\Jobs\Middleware;

class ArticleSyncControl
{
    public function handle($job, $next)
    {
        if (is_wgr_active()) {
            $next($job);
        }
        else {
            $job->release(3600);
        }
    }
}
