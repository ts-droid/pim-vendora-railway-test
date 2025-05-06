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

                    <!-- Greeting -->
                    <tr>
                        <td align="center">
                            <h1 style="font-size:1.5rem;font-weight:300;margin:0 0 10px 0;">{{ __('order_confirm_title', ['name' => $salesOrder->billingAddress->first_name ?? '']) }}</h1>
                            <p style="margin:0 0 10px 0;">{{ __('order_confirm_text_1') }}</p>
                            <p style="margin:0 0 10px 0;">{{ __('order_confirm_text_2') }}</p>
                        </td>
                    </tr>

                    <!-- Products -->
                    <tr>
                        <td style="padding: 10px 0;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">
                                <tr>
                                    <th align="left" colspan="3" style="color:#888888;font-weight:300;border-bottom:1px solid #e7e7e7;padding:8px 0;">{{ __('order_confirm_items') }}</th>
                                </tr>
                                @if($salesOrder->lines ?? false)
                                    @foreach($salesOrder->lines as $salesOrderLine)
                                        @if($salesOrderLine->article_number === 'SHIP25')
                                            @continue
                                        @endif

                                        <tr>
                                            <td style="vertical-align: top;width: 90px;padding-top: 8px;">
                                                <img src="{{ get_article_image($salesOrderLine->article_number) }}" style="background-color: #F5F5F5;height: 75px;width: 75px;" height="75" width="75" />
                                            </td>
                                            <td style="vertical-align: top;padding-top: 8px;">
                                                {{ $salesOrderLine->description }}<br>
                                                {{ __('order_confirm_quantity') }}: {{ $salesOrderLine->quantity }}
                                            </td>
                                            <td style="vertical-align: top;text-align: end;padding-top: 8px;">
                                                {{ number_format(($salesOrderLine->unit_price * $salesOrderLine->quantity), 2, '.', ' ') }} {{ $salesOrder->currency }}
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </table>
                        </td>
                    </tr>

                    <!-- Shipping Address -->
                    <tr>
                        <td style="padding: 10px 0;">
                            <b>{{ __('order_confirm_shipping_address') }}:</b><br>
                            @if($salesOrder->shippingAddress ?? false)
                                {{ $salesOrder->shippingAddress->full_name }}<br>
                                {{ $salesOrder->shippingAddress->street_line_1 }}<br>
                                @if($salesOrder->shippingAddress->street_line_2)
                                    {{ $salesOrder->shippingAddress->street_line_2 }}<br>
                                @endif
                                {{ $salesOrder->shippingAddress->postal_code }} {{ $salesOrder->shippingAddress->city }}<br>
                                {{ $salesOrder->shippingAddress->country_code }}
                            @endif
                        </td>
                    </tr>

                    <!-- Summary box -->
                    <tr>
                        <td style="padding:20px 0;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f5f5;padding:16px;">
                                <tr>
                                    <td align="left" style="padding-bottom: 4px;">{{ __('order_confirm_items') }}</td>
                                    <td align="right" style="padding-bottom: 4px;">{{ $salesOrder->lines ? $salesOrder->lines->sum('quantity') : 0 }}</td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-bottom: 4px;">{{ __('order_confirm_sub_total') }}</td>
                                    <td align="right" style="padding-bottom: 4px;">{{ number_format($salesOrder->order_total, 2, '.', ' ') }} {{ $salesOrder->currency }}</td>
                                </tr>
                                <tr>
                                    <td align="left" style="font-weight:bold;">{{ __('order_confirm_total') }}</td>
                                    <td align="right" style="font-weight:bold;">{{ number_format($salesOrder->order_total, 2, '.', ' ') }} {{ $salesOrder->currency }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Order details -->
                    <tr>
                        <td style="padding: 10px 0;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <th align="left" colspan="2" style="color:#888888;font-weight:300;border-bottom:1px solid #e7e7e7;padding:8px 0;">Order details</th>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top:10px;">
                                        <p style="margin:0;"><b>{{ __('order_confirm_date') }}:</b> {{ $salesOrder->date }}</p>
                                        <p style="margin:0;"><b>{{ __('order_confirm_number') }}:</b> {{ $salesOrder->order_number }}</p>
                                        <p style="margin:0;"><b>{{ __('order_confirm_pay_method') }}:</b> {{ $salesOrder->pay_method }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top:10px;">
                                        <p style="margin:0;font-weight:bold;">{{ __('order_confirm_billing_address') }}:</p>
                                        @if($salesOrder->billingAddress ?? false)
                                            <p style="margin:0;">{{ $salesOrder->billingAddress->full_name }}</p>
                                            <p style="margin:0;">{{ $salesOrder->billingAddress->street_line_1 }}</p>
                                            @if($salesOrder->billingAddress->street_line_2)
                                                <p style="margin:0;">{{ $salesOrder->billingAddress->street_line_2 }}</p>
                                            @endif
                                            <p style="margin:0;">{{ $salesOrder->billingAddress->postal_code }} {{ $salesOrder->billingAddress->city }}</p>
                                            <p style="margin:0;">{{ $salesOrder->billingAddress->country_code }}</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Company information -->
                    <tr>
                        <td style="padding: 20px 0;">
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
