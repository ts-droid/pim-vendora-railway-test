<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponseController extends Controller
{
    public static function success(array $data = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'error_message' => null,
        ]);
    }

    public static function error(string $errorMessage): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => [],
            'error_message' => $errorMessage,
        ]);
    }
}
