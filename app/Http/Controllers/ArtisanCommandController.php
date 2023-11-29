<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ArtisanCommandController extends Controller
{
    public function run(Request $request)
    {
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
