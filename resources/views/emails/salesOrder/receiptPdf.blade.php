@php
    $shipping = 0;
    $discount = 0;

    if ($salesOrder->lines ?? false) {
        foreach ($salesOrder->lines as $salesOrderLine) {
            if ($salesOrderLine->article_number == 'SHIP25') {
                $shipping += add_vat($salesOrderLine->unit_price * $salesOrderLine->quantity, $salesOrderLine->vat_rate);
            }
            if ($salesOrderLine->article_number == 'DISC25') {
                $discount += add_vat($salesOrderLine->unit_price * $salesOrderLine->quantity, $salesOrderLine->vat_rate);
            }
        }
    }

@endphp

<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <style>
            * {
                font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
            }
            h1 {
                font-size: 28px;
                font-weight: 400;
            }
            p {
                font-size: 16px;
            }
        </style>
    </head>

    <body>
        <table style="width: 100%;">
            <tr>
                <td>
                    <h1>{{ __('receipt_title') }}</h1>

                    <p>
                        <b>{{ __('order_confirm_date') }}:</b> {{ $salesOrder->date }}<br>
                        <b>{{ __('order_confirm_number') }}:</b> {{ $salesOrder->order_number }}<br>
                        <b>{{ __('order_confirm_pay_method') }}:</b> {{ $salesOrder->pay_method }}
                    </p>
                </td>
            </tr>
        </table>

        <table style="width: 100%;">
            <tr>
                <td style="width: 50%;">
                    <b>{{ __('billing_address') }}:</b><br>
                    {{ $salesOrder->billingAddress->full_name ?? '' }}<br>
                    {{ $salesOrder->billingAddress->street_line_1 ?? '' }}<br>
                    @if($salesOrder->billingAddress->street_line_2 ?? false)
                        {{ $salesOrder->billingAddress->street_line_2 ?? '' }}<br>
                    @endif
                    {{ $salesOrder->billingAddress->postal_code ?? '' }} {{ $salesOrder->billingAddress->city ?? '' }}<br>
                    {{ get_country_name(($salesOrder->billingAddress->country_code ?? ''), app()->getLocale()) }}
                    @if($salesOrder->vat_number)
                        <br>{{ $salesOrder->vat_number }}
                    @endif
                </td>
                <td style="width: 50%;">
                    <b>{{ __('shipping_address') }}:</b><br>
                    {{ $salesOrder->shippingAddress->full_name ?? '' }}<br>
                    {{ $salesOrder->shippingAddress->street_line_1 ?? '' }}<br>
                    @if($salesOrder->shippingAddress->street_line_2 ?? false)
                        {{ $salesOrder->shippingAddress->street_line_2 ?? '' }}<br>
                    @endif
                    {{ $salesOrder->shippingAddress->postal_code ?? '' }} {{ $salesOrder->shippingAddress->city ?? '' }}<br>
                    {{ get_country_name(($salesOrder->shippingAddress->country_code ?? ''), app()->getLocale()) }}
                </td>
            </tr>
        </table>


        <table style="width: 100%;">
            <tr>
                <td colspan="2">
                    <p><b>{{ __('order_confirm_items') }}</b></p>
                </td>
            </tr>

            @if($salesOrder->lines ?? false)
                @foreach($salesOrder->lines as $salesOrderLine)
                    @continue($salesOrderLine->article_number === 'SHIP25' || $salesOrderLine->article_number === 'DISC25')

                    <tr>
                        <td style="width: 70%;">{{ $salesOrderLine->quantity }} {{ __('pcs') }} - {{ $salesOrderLine->description }}</td>
                        <td style="width: 30%;text-align: right;">
                            @if($salesOrderLine->active_unit_price > 0 && $salesOrderLine->active_unit_price < $salesOrderLine->unit_price)
                                <div style="text-decoration: line-through;">{{ number_format((add_vat($salesOrderLine->unit_price * $salesOrderLine->quantity, $salesOrderLine->vat_rate)), 2, '.', ' ') }} {{ $salesOrder->currency }}</div>
                                <div>{{ number_format((add_vat($salesOrderLine->active_unit_price * $salesOrderLine->quantity, $salesOrderLine->vat_rate)), 2, '.', ' ') }} {{ $salesOrder->currency }}</div>
                            @else
                                {{ number_format((add_vat($salesOrderLine->unit_price * $salesOrderLine->quantity, $salesOrderLine->vat_rate)), 2, '.', ' ') }} {{ $salesOrder->currency }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endif

            <tr>
                <td style="width: 70%;padding-top: 12px;text-align: right;">{{ __('order_confirm_sub_total') }}</td>
                <td style="width: 30%;padding-top: 12px;text-align: right;">{{ number_format($salesOrder->getOrderSubtotal(), 2, '.', ' ') }} {{ $salesOrder->currency }}</td>
            </tr>

            @if($shipping !== 0)
                <tr>
                    <td style="width: 70%;text-align: right;">{{ __('order_confirm_shipping') }}</td>
                    <td style="width: 30%;text-align: right;">+{{ number_format($shipping, 2, '.', ' ') }} {{ $salesOrder->currency }}</td>
                </tr>
            @endif

            @if($discount !== 0)
                <tr>
                    <td style="width: 70%;text-align: right;">{{ __('order_confirm_discount') }}</td>
                    <td style="width: 30%;text-align: right;">-{{ number_format(($discount * -1), 2, '.', ' ') }} {{ $salesOrder->currency }}</td>
                </tr>
            @endif

            <tr>
                <td style="width: 70%;text-align: right;"><b>{{ __('order_confirm_total') }}</b></td>
                <td style="width: 30%;text-align: right;"><b>{{ number_format($salesOrder->getOrderTotalWithVat(), 2, '.', ' ') }} {{ $salesOrder->currency }}</b></td>
            </tr>
            <tr>
                <td style="width: 70%;text-align: right;">{{ __('order_confirm_vat_total') }}</td>
                <td style="width: 30%;text-align: right;">{{ number_format($salesOrder->getOrderTotalWithVat() - $salesOrder->order_total, 2, '.', ' ') }} {{ $salesOrder->currency }}</td>
            </tr>
        </table>

        <table style="width: 100%;">
            <tr>
                <td>
                    @if(($brandingData['brand_name'] ?? '') != 'Vendora Nordic AB')
                        <p><b>{{ __('order_confirm_distributor') }} {{ $brandingData['brand_name'] ?? '' }}</b></p>
                    @endif
                    <p>
                        Vendora Nordic AB<br>
                        Ladugårdsvägen 1<br>
                        234 35 Lomma<br>
                        Sweden<br>
                        {{ __('order_confirm_email') }}: info@vendora.se<br>
                        {{ __('order_confirm_org_nr') }}: 556843-5456<br>
                        {{ __('order_confirm_vat_nr') }}: SE556843545601<br>
                        {{ __('order_confirm_hq') }}: Lomma
                    </p>
                </td>
            </tr>
        </table>

    </body>
</html>
