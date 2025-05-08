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
                            <h1 style="font-size:1.5rem;font-weight:300;margin:0 0 10px 0;">{{ __('tracking_number_title', ['name' => $salesOrder->billingAddress->first_name ?? '']) }}</h1>
                            <p style="margin:0 0 20px 0;">{{ __('tracking_number_text_1') }}</p>

                            <p style="margin:0 0 4px 0;font-weight: bold;">{{ __('tracking_number_sub_title') }}:</p>
                            <p style="margin:0 0 20px 0;">{{ $trackingNumber }}</p>

                            <p style="margin:0 0 10px 0;">{{ __('tracking_number_text_2') }}</p>
                        </td>
                    </tr>

                    <!-- Company information -->
                    <tr>
                        <td style="padding: 20px 0;" align="center">
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
