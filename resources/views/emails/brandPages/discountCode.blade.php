<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $emailSubject }}</title>
</head>

<body style="margin:0;padding:0 32px;background-color:#ffffff;text-align:center;font-family:Arial, Helvetica, sans-serif;font-size:0.95rem;line-height:1.3;">

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">

                <table width="625" cellpadding="0" cellspacing="0" border="0" style="margin:auto;max-width:625px;width:100%;text-align:left;">

                    <!-- Logo -->
                    <tr>
                        <td align="center" style="padding: 20px 0;">
                            <img src="{{ \App\Services\EmailImageService::prepareImageForEmail($brandingData['logo_url']) }}" alt="Logo" style="height: 28px;margin-bottom: 1rem;" />
                        </td>
                    </tr>

                    <!-- Information -->
                    <tr>
                        <td align="center">
                            <h1 style="font-size:1.5rem;font-weight:300;margin:0 0 10px 0;">{{ get_phrase('brand_page_discount_code_title', ['discount' => $discountPercent]) }}</h1>

                            <p style="margin:0 0 20px 0;">{{ get_phrase('brand_page_discount_code_text_1', ['discount' => $discountPercent]) }}</p>

                            <div style="display: inline-block;background-color: #f6f6f6;padding: 1rem 1.5rem;font-size: 22px;margin-bottom: 20px;border: 3px dashed #CCCCCC;border-radius: 100px;">{{ $discountCode }}</div>

                            <p style="margin:0 0 20px 0;">{{ get_phrase('brand_page_discount_code_text_2', ['discount' => $discountPercent]) }}</p>

                            <a href="{{ $discountCodeUrl }}" target="_blank" style="background-color: #1A1C1F;color: #ffffff !important;text-decoration: none !important;padding: 0.75rem 1.5rem;display: inline-block;border-radius: 100px;margin-bottom: 20px;">{{ get_phrase('brand_page_discount_code_button', ['discount' => $discountPercent]) }}</a>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
