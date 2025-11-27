<?php

namespace App\Actions\Mail;

use App\Enums\LaravelQueues;
use App\Mail\RawMail;
use App\Services\BrandPageService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;

class BrandPageDiscountCode
{
    public function execute(string $source, string $language, string $email, string $discountCode): void
    {
        App::setLocale($language);

        // Fetch brand page data
        $endpoint = 'https://' . $source . '/api/v1/pages/site/get-by-domain';

        $brandPageService = new BrandPageService();
        $response = $brandPageService->callAPI('GET', $endpoint, [
            'domain' => $source
        ]);

        if (!($response['success'] ?? false)) {
            return;
        }

        $site = $response['data'];
        $discountPercent = $site['discount_model_percent'];

        $brandingData = [
            'brand_name' => $site['official_name'],
            'logo_url' => 'https://' . $site['domain'] . '/storage/' . ($site['logo']['path'] ?? ''),
            'logo_path' => null,
            'language_code' => $language,
        ];

        $emailSubject = __('brand_page_discount_code_subject', ['discount' => $discountPercent]);
        $emailFromEmail = 'info@vendora.se';
        $emailFromName = $brandingData['brand_name'];

        $emailBody = view('emails.brandPages.discountCode', [
            'brandingData' => $brandingData,
            'emailSubject' => $emailSubject,
            'discountPercent' => $discountPercent,
            'discountCode' => $discountCode
        ])->render();

        $mail = (new RawMail($emailSubject, $emailBody, $emailFromEmail, $emailFromName))
            ->onQueue(LaravelQueues::MAIL->value);

        Mail::to($email)->queue($mail);
    }
}
