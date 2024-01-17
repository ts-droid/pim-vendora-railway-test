<?php

namespace App\Http\Controllers;

use App\Services\VismaNet\VismaNetApiService;
use Illuminate\Http\Request;

class VismaNetTestController extends Controller
{
    public function index()
    {
        return view('vismanet.test');
    }

    public function send(Request $request)
    {
        $body = [];

        if ($request->input('body')) {
            $body = json_decode($request->input('body'), true);
        }

        $apiService = new VismaNetApiService();
        $response = $apiService->callAPI(
            $request->input('method'),
            $request->input('endpoint'),
            $body
        );

        echo '<pre>';
        echo json_encode($response, JSON_PRETTY_PRINT);
        echo '</pre>';
    }
}
