<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsletterController extends Controller
{
    public function get(Request $request)
    {
        $query = NewsletterSubscriber::query();

        if ($request->has('source')) {
            $query->where('source', $request->input('source'));
        }

        $subscribers = $query->get();

        return ApiResponseController::success($subscribers->toArray());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|unique:newsletter_subscribers,email',
            'source' => 'string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $newsletterSubscriber = NewsletterSubscriber::create([
            'email' => $request->input('email'),
            'source' => $request->input('source', ''),
            'first_name' => $request->input('first_name', ''),
            'last_name' => $request->input('last_name', ''),
        ]);

        return ApiResponseController::success($newsletterSubscriber->toArray());
    }
}
