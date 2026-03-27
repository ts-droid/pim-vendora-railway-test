<?php

namespace App\Http\Controllers;

use App\Actions\Mail\BrandPageDiscountCode;
use App\Models\NewsletterSubscriber;
use App\Services\MailerCheckService;
use App\Services\MailerLiteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NewsletterController extends Controller
{
    public function get(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $query = NewsletterSubscriber::query();

        if ($request->has('source')) {
            $query->where('source', $request->input('source'));
        }

        $subscribers = $query->get();

        return ApiResponseController::success($subscribers->toArray());
    }

    public function exists(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

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
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

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
        $tag = $request->input('tag', 'form');

        // Check if email is already unsubscribed
        $isUnsubscribed = DB::table('newsletter_unsubscribed')
            ->where('email', $email)
            ->where(function ($query) use ($source) {
                $query->where('source', $source)
                    ->orWhere('source', 'all');
            })
            ->exists();

        if ($isUnsubscribed) {
            return ApiResponseController::error('This emails have been unsubscribed from receiving newsletters.');
        }

        // Validate email
        $mailerCheck = new MailerCheckService();
        $validEmail = $mailerCheck->checkSingle($email);

        if (!$validEmail) ApiResponseController::error('Invalid email address.');

        $existingSubscriber = NewsletterSubscriber::where('email', $email)
            ->where('source', $request->input('source', $source))
            ->first();

        if ($existingSubscriber) {
            // Update tags
            $tags = explode(',', $existingSubscriber->tag);
            $tags[] = $tag;
            $tags = array_filter(array_unique($tags));

            $existingSubscriber->update(['tag' => implode(',', $tags)]);

            return ApiResponseController::success($existingSubscriber->toArray());
        }

        $newsletterSubscriber = NewsletterSubscriber::create([
            'email' => $email,
            'language' => $locale,
            'source' => $source,
            'first_name' => $request->input('first_name', ''),
            'last_name' => $request->input('last_name', ''),
            'tag' => $tag,
        ]);

        $mailerLiteService = new MailerLiteService();
        $mailerLiteService->addSubscriber($email, $source);

        if ($discountCode) {
            (new BrandPageDiscountCode)->execute($source, $locale, $email, $discountCode);
        }

        return ApiResponseController::success($newsletterSubscriber->toArray());
    }

    public function unsubscribe(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'source' => 'string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        $email = trim(mb_strtolower($request->input('email')));
        $source = trim(mb_strtolower($request->input('source')));
        $source = $source ?: 'all';

        // Fetch the subscribers language
        $language = NewsletterSubscriber::where('email', $email)
            ->where(function ($query) use ($source) {
                if ($source != 'all') {
                    $query->where('source', $source);
                }
            })
            ->value('language');

        // Remove from subscription table
        $removeQuery = NewsletterSubscriber::query();
        $removeQuery->where('email', $email);
        if ($source != 'all') {
            $removeQuery->where('source', $source);
        }
        $removeQuery->delete();

        // Insert into unsubscribed table
        DB::table('newsletter_unsubscribed')->insertOrIgnore([
            'email' => $email,
            'source' => $source,
            'language' => $language ?: 'en',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponseController::success();
    }

    public function send(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $validator = Validator::make($request->all(), [
            'tag' => 'required|string',
            'subject_en' => 'required|string',
            'body_en' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return ApiResponseController::error($errors[0]);
        }

        // TODO: Queue the newsletter here

        return ApiResponseController::success();
    }
}
