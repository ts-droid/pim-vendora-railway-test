<?php

namespace App\Http\Controllers;

use App\Actions\Mail\BrandPageDiscountCode;
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

    public function exists(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'source' => 'string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $email = mb_strtolower($request->input('email'));
        $source = mb_strtolower($request->input('source'));

        $existingSubscriber = NewsletterSubscriber::where('email', $email)
            ->where('source', $request->input('source', $source))
            ->first();

        return ApiResponseController::success([
            'exists' => $existingSubscriber ? 1 : 0
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'source' => 'sometimes|string',
            'tag' => 'sometimes|string',
            'discount_code' => 'sometimes|string',
            'locale' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $email = mb_strtolower($request->input('email'));
        $source = mb_strtolower($request->input('source'));
        $discountCode = $request->input('discount_code');
        $locale = $request->input('locale', 'en');

        $existingSubscriber = NewsletterSubscriber::where('email', $email)
            ->where('source', $request->input('source', $source))
            ->first();

        if ($existingSubscriber) {
            return ApiResponseController::success($existingSubscriber->toArray());
        }

        $newsletterSubscriber = NewsletterSubscriber::create([
            'email' => $email,
            'language' => $locale,
            'source' => $source,
            'first_name' => $request->input('first_name', ''),
            'last_name' => $request->input('last_name', ''),
            'tag' => $request->input('tag', 'form'),
        ]);

        if ($discountCode) {
            (new BrandPageDiscountCode)->execute($source, $locale, $email, $discountCode);
        }

        return ApiResponseController::success($newsletterSubscriber->toArray());
    }
}
