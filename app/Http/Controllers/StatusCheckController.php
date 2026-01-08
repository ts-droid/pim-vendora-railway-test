<?php

namespace App\Http\Controllers;

use App\Models\StatusIndicator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatusCheckController extends Controller
{
    public function checkStatus()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $databaseOnline = (bool) (DB::connection()->getPdo());

        $status = [
            'app_name' => config('app.name'),
            'environment' => app()->environment(),
            'database_connection' => $databaseOnline ? 'Online' : 'Offline',
            'cache_driver' => config('cache.default'),
            'cache_status' => $this->checkCache() ? 'Operational' : 'Not Operational',
            'session_driver' => config('session.driver'),
            'session_status' => $this->checkSession() ? 'Operational' : 'Not Operational',
            'status_indicators' => $this->getStatusIndicators(),
            // Add more status checks as needed
        ];

        return response()
            ->json($status)
            ->setEncodingOptions(JSON_PRETTY_PRINT);
    }

    public function getStatusIndicators()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $statusIndicators = StatusIndicator::orderBy('title', 'ASC')->get();

        $response = [];

        foreach ($statusIndicators as $statusIndicator) {

            $response[] = [
                'title' => $statusIndicator->title,
                'status' => ($statusIndicator->isGreen() ? 'UP' : 'DOWN'),
            ];
        }

        return $response;
    }

    public function checkCache()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        try {
            // Attempt to write and read from cache
            Cache::put('cache_test_key', 'test_value', 60); // Store a value for 60 seconds
            $value = Cache::get('cache_test_key');

            if ($value === 'test_value') {
                return true; // Cache is working
            }

            return false; // Cache is not working correctly
        } catch (\Exception $e) {
            return false; // An error occurred, cache is not operational
        }
    }

    public function checkSession()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        try {
            // Attempt to write and read from the session
            session(['session_test_key' => 'test_value']);
            $value = session('session_test_key');

            if ($value === 'test_value') {
                return true; // Session is working
            }

            return false; // Session is not working correctly
        } catch (\Exception $e) {
            return false; // An error occurred, session is not operational
        }
    }
}
