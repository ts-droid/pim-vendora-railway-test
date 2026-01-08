<?php

namespace App\Http\Controllers;

use http\Env\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponseController extends Controller
{
    public static function success(array $data = [])
    {
        $__controllerLogContext = static::controllerStaticLogContext(__FUNCTION__, func_get_args());
        action_log('Invoked controller static method.', $__controllerLogContext);

        return response()->json([
            'success' => true,
            'data' => $data,
            'error_message' => null,
        ]);
    }

    public static function error(string $errorMessage): JsonResponse
    {
        $__controllerLogContext = static::controllerStaticLogContext(__FUNCTION__, func_get_args());
        action_log('Invoked controller static method.', $__controllerLogContext);

        return response()->json([
            'success' => false,
            'data' => [],
            'error_message' => $errorMessage,
        ]);
    }

    public static function getDataFromResponse($response)
    {
        $__controllerLogContext = static::controllerStaticLogContext(__FUNCTION__, func_get_args());
        action_log('Invoked controller static method.', $__controllerLogContext);

        $response = json_decode($response->content(), true);

        if (isset($response['data']['results']) && is_array($response['data']['results'])) {
            return $response['data']['results'];
        }

        return $response['data'];
    }
}
