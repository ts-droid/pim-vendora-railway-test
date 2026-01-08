<?php

namespace App\Http\Controllers;

use App\Jobs\QueueCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ArtisanCommandController extends Controller
{
    public function queue(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $command = $request->input('command');
        $arguments = $request->input('arguments') ?: [];

        if (!$command) {
            return ApiResponseController::error('No command provided.');
        }

        QueueCommand::dispatch($command, $arguments);

        return ApiResponseController::success(['message' => 'Command queued.']);
    }

    public function run(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $command = $request->input('command');

        if (!$command) {
            return response()->json([
                'success' => false,
                'message' => 'No command provided.',
            ], 400);
        }

        // Is any arguments provided?
        $arguments = [];

        foreach ($request->input('arguments') as $argument => $value) {
            $arguments[$argument] = $value;
        }

        Artisan::call($command, $arguments);

        return response()->json([
            'success' => true,
            'message' => 'Command executed.',
        ]);
    }
}
