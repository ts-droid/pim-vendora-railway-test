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
                        <img src="{{ \App\Services\EmailImageService::prepareImageForEmail($brandingData['logo_url']) }}" alt="Logo" style="height: {{ ($brandingData['logo_multiplier'] * 28) }}px;margin-bottom: 1rem;" />
                    </td>
                </tr>

                <!-- Information -->
                <tr>
                    <td align="center">
                        <h1 style="font-size:1.75rem;font-weight:600;margin:0 0 10px 0;">
                            Black Friday<br>
                            20% off everything in our store
                        </h1>

                        <br>

                        <img src="{{ \App\Services\EmailImageService::prepareImageForEmail('https://vendora.ams3.cdn.digitaloceanspaces.com/assets/brand_pages_black_friday.png') }}" style="width: 100%;">

                        <br><br>

                        <a href="{{ $brandingData['site_url'] }}/en" target="_blank" style="font-size:1.5rem;font-weight:600;color: #000000 !important;text-decoration: underline;">{{ 'www.' . $brandingData['domain'] }}</a>
                    </td>
                </tr>

                <!-- Company information -->
                <tr>
                    <td align="center" style="padding: 20px 0;text-align: center;font-size: 14px;">
                        @if($brandingData['brand_name'] != 'Vendora Nordic ABa')
                            <p style="font-weight: bold;margin-bottom: 8px;">{{ get_phrase('order_confirm_distributor') }} {{ $brandingData['brand_name'] }}</p>
                        @endif
                            <p style="margin: 0;">Vendora Nordic AB</p>
                            <p style="margin: 0;">Ladugårdsvägen 1</p>
                            <p style="margin: 0;">234 35 Lomma</p>
                            <p style="margin: 0;">Sweden</p>
                            <p style="margin: 0;">{{ get_phrase('order_confirm_email') }}: info@vendora.se</p>
                            <p style="margin: 0;">{{ get_phrase('order_confirm_org_nr') }}: 556843-5456</p>
                            <p style="margin: 0;">{{ get_phrase('order_confirm_vat_nr') }}: SE556843545601</p>
                            <p style="margin: 0;">{{ get_phrase('order_confirm_hq') }}: Lomma</p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
