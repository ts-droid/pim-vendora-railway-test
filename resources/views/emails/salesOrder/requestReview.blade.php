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
                            <div>
                                @for($rating = 1;$rating <=5;$rating++)
                                    <a href="{{ route('customer.review', ['article_id' => $line->article->id, 'lang' => app()->getLocale(), 'rating' => $rating]) }}" style="text-decoration: none !important;color: #000000 !important;margin-right: 6px;display: inline-block;">
                                        <img src="{{ \App\Services\EmailImageService::prepareBase64ImageForEmail('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABYAAAAWCAYAAADEtGw7AAAABmJLR0QA/wD/AP+gvaeTAAABjUlEQVQ4ja3Uv0uVYRjG8c/xlCepqT/AxRwMc2pokBaJpv4ACWtQCTIIW5yiRof+g2hscYmGoKBIIaFmDaEfEpGBNEQiNOlxeO+Djw/p+6RecHPec9/X9T33e96Hl3L1Rh2rurCMj2geJ3gU7ajRkkCjwNNUbfs7wGcxiK3D7bir6wG8EtWO3pHUxAoWk94CPuHEUcBjqg1Hkt5I9MYOCjZiq16cy6offfiAy1luAZfwFZ/xJavvjbjoS0J/M9PjCKfqx2S2SE8yX4XZuLXF2LzkpORqRPZdsGY7g5loPHW4h9LEkxza0R1s4zla/wE9ibmA3t/PdEt18F/Y+5/tpxaexUJ368w3Av6gAPwwvDfzQdc/zJ3b2igA/wnvXAn4vOphLBWAl8I7UAK+EJ/LWf9qVA6GoYIlPMKv5Psw5u2+Nt/jWjJfj0ytXuJNAN8G7BsmMB7X7fixYbzGqxLwGjYjvIYpdCfzbtzGj/Bs4mcdtKU6Puu45+Cz3IPp8G7hVB38Is7UmRKdjswe7QAcxmBwClrF6QAAAABJRU5ErkJggg==') }}" width="22" height="22" style="margin-bottom: 16px;">
                                    </a>
                                @endfor
                            </div>
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
