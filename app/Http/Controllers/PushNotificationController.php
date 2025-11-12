<?php

namespace App\Http\Controllers;

use App\Models\AppUser;
use Illuminate\Http\Request;

class PushNotificationController extends Controller
{
    public function setPushToken(Request $request)
    {
        $displayName = get_display_name();
        $token = $request->input('token');

        if (!$token) {
            return ApiResponseController::error('No token provided');
        }

        $user = AppUser::where('push_token', $token)->first();

        if ($user) {
            $user->update([
                'display_name' => $displayName,
            ]);
        } else {
            $user = AppUser::create([
                'display_name' => $displayName,
                'push_token' => $token,
            ]);
        }

        return ApiResponseController::success($user->toArray());
    }
}
