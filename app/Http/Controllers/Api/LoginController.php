<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $email = $request->input('email');
        $password = $request->input('password');

        $user = User::where('email', $email)->first();
        if (!$user) {
            return ApiResponseController::error('Invalid credentials');
        }

        if (!Hash::check($password, $user->password)) {
            return ApiResponseController::error('Invalid credentials');
        }

        $user->update([
            'auth_token' => Str::random(32)
        ]);

        return ApiResponseController::success($user->toArray());
    }

    public function createUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|string',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $email = trim(mb_strtolower($request->input('email')));

        $emailTaken = User::where('email', $email)->exists();
        if ($emailTaken) {
            return ApiResponseController::error('Email already taken');
        }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $email,
            'password' => Hash::make($request->input('password'))
        ]);

        return ApiResponseController::success($user->toArray());
    }
}
