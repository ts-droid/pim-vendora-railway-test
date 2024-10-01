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
            $newJob = clone $job;

            $job->delete();

            dispatch($newJob)->delay(now()->addSeconds(3600));
        }
    }
}
