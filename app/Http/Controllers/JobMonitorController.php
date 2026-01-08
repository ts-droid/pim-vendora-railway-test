<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobMonitorController extends Controller
{
    public function dashboard()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $workload = DB::table('jobs')
            ->select('queue', DB::raw('count(*) as num_jobs'))
            ->groupBy('queue')
            ->get();

        $jobsInQueue = $workload->sum('num_jobs');

        $failedJobs = DB::table('failed_jobs')
            ->where('connection', 'database')
            ->count();

        return view('jobMonitor.dashboard', compact('jobsInQueue', 'failedJobs', 'workload'));
    }

    public function queue()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $jobs = DB::table('jobs')->get();

        if ($jobs) {
            foreach ($jobs as &$job) {
                $payload = json_decode($job->payload, true);
                $job->displayName = $payload['displayName'];
            }
        }

        return view('jobMonitor.queue', compact('jobs'));
    }

    public function failedJobs(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $filter = [
            'displayName' => $request->get('displayName'),
        ];

        $failedJobs = DB::table('failed_jobs')
            ->where('connection', 'database')
            ->orderBy('failed_at', 'DESC')
            ->get();
        $displayNames = [];

        if ($failedJobs) {
            foreach ($failedJobs as &$job) {
                $payload = json_decode($job->payload, true);
                $job->displayName = $payload['displayName'];
            }

            $displayNames = array_column($failedJobs->toArray(), 'displayName');
            $displayNames = array_unique($displayNames);

            if ($filter['displayName']) {
                $failedJobs = $failedJobs->filter(function($job) use ($filter) {
                    return $job->displayName === $filter['displayName'];
                });
            }
        }

        return view('jobMonitor.failedJobs', compact('failedJobs', 'displayNames', 'filter'));
    }

    public function retryJobs(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $jobIDs = $request->get('jobIDs', '');
        $jobIDs = explode(',', $jobIDs);
        $jobIDs = array_map('intval', $jobIDs);
        $jobIDs = array_filter($jobIDs);

        foreach ($jobIDs as $jobID) {
            $this->requeueJob($jobID);
        }

        return response()->json();
    }

    public function deleteJobs(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $jobIDs = $request->get('jobIDs', '');
        $jobIDs = explode(',', $jobIDs);
        $jobIDs = array_map('intval', $jobIDs);
        $jobIDs = array_filter($jobIDs);

        foreach ($jobIDs as $jobID) {
            DB::table('failed_jobs')->where('id', $jobID)->delete();
        }

        return response()->json();
    }

    private function requeueJob(int $failedJobID)
    {
        $job = DB::table('failed_jobs')
            ->where('id', $failedJobID)
            ->where('connection', 'database')
            ->first();

        if (!$job) {
            return;
        }

        // Insert into the queue again
        DB::table('jobs')->insert([
            'queue' => $job->queue,
            'payload' => $job->payload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        // Remove the failed job row
        DB::table('failed_jobs')->where('id', $failedJobID)->delete();
    }
}
