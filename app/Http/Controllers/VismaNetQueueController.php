<?php

namespace App\Http\Controllers;

use App\Services\VismaNet\VismaNetQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VismaNetQueueController extends Controller
{
    public function queue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required',
            'method' => 'required',
            'endpoint' => 'required',
            'body' => 'required',
            'process_at' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $queueService = new VismaNetQueueService();

        $queueService->queue(
            $request->post('type'),
            $request->post('order_number', ''),
            $request->post('external_order_number', ''),
            $request->post('method'),
            $request->post('endpoint'),
            $request->post('body'),
            $request->post('process_at')
        );

        return ApiResponseController::success();
    }
}
