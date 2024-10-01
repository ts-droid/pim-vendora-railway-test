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
            $job->delete();

            $job->dispatch($job)->delay(now()->addSeconds(3600));
        }
    }
}
