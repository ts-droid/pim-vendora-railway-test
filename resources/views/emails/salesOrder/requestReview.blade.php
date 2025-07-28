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
                        <p style="margin:0 0 20px 0;">{{ __('request_review_text') }}</p>
                    </td>
                </tr>

                @foreach($salesOrder->lines as $line)
                    @continue($line->article_number === 'SHIP25' || $line->article_number === 'DISC25' || !$line->article)

                    <tr>
                        <td align="center">
                            <img src="{{ \App\Services\EmailImageService::prepareImageForEmail($line->article->getMainImage() ?? '') }}" style="height: 65px;width: 65px;">
                            @for($rating = 1;$rating <=5;$rating++)
                                <a href="{{ route('customer.review', ['article_id' => $line->article->id, 'lang' => app()->getLocale(), 'rating' => $rating]) }}" style="text-decoration: none !important;color: #000000 !important;margin-right: 6px;display: inline-block;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-star" viewBox="0 0 16 16" style="margin-bottom: 16px;">
                                        <path d="M2.866 14.85c-.078.444.36.791.746.593l4.39-2.256 4.389 2.256c.386.198.824-.149.746-.592l-.83-4.73 3.522-3.356c.33-.314.16-.888-.282-.95l-4.898-.696L8.465.792a.513.513 0 0 0-.927 0L5.354 5.12l-4.898.696c-.441.062-.612.636-.283.95l3.523 3.356-.83 4.73zm4.905-2.767-3.686 1.894.694-3.957a.56.56 0 0 0-.163-.505L1.71 6.745l4.052-.576a.53.53 0 0 0 .393-.288L8 2.223l1.847 3.658a.53.53 0 0 0 .393.288l4.052.575-2.906 2.77a.56.56 0 0 0-.163.506l.694 3.957-3.686-1.894a.5.5 0 0 0-.461 0z"/>
                                    </svg>
                                </a>
                            @endfor
                        </td>
                    </tr>
                @endforeach

                <!-- Company information -->
                <tr>
                    <td style="padding: 20px 0;" align="center" >
                        @if($brandingData['brand_name'] != 'Vendora Nordic AB')
                            <p style="font-weight: bold;margin-bottom: 8px;">{{ __('order_confirm_distributor') }} {{ $brandingData['brand_name'] }}</p>
                        @endif
                        <p style="margin: 0;">Vendora Nordic AB</p>
                        <p style="margin: 0;">Ladugårdsvägen 1</p>
                        <p style="margin: 0;">234 35 Lomma</p>
                        <p style="margin: 0;">Sweden</p>
                        <p style="margin: 0;">{{ __('order_confirm_email') }}: info@vendora.se</p>
                        <p style="margin: 0;">{{ __('order_confirm_org_nr') }}: 556843-5456</p>
                        <p style="margin: 0;">{{ __('order_confirm_vat_nr') }}: SE556843545601</p>
                        <p style="margin: 0;">{{ __('order_confirm_hq') }}: Lomma</p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>

</html>
