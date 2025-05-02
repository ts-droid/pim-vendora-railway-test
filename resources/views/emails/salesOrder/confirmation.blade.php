<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <title>{{ $emailSubject }}</title>

    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 0.95rem;
            line-height: 1.3;
        }

        body {
            text-align: center;
        }

        .container {
            width: 100%;
            max-width: 625px;
            margin: 0 auto;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 300;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
        }
        .order-table th {
            color: #888888;
            font-weight: 300;
            border-bottom: 1px #e7e7e7 solid;
        }
        .order-table th,
        .order-table td {
            text-align: left;
            padding: 0.5rem 0.5rem;
            vertical-align: top;
        }
        .order-table td:last-child {
            text-align: right;
        }
        .item-data {
            display: flex;
        }
        .item-data__image {
            width: 75px;
            height: 75px;
            margin-right: 16px;
            background-color: #F5F5F5;
        }
        .item-data__description {
            margin-bottom: 4px;
        }

        .text-start {
            text-align: left !important;
        }
        .text-end {
            text-align: right !important;
        }

        table {
            width: 100%;
        }

        .box {
            background-color: #F5F5F5;
            padding: 16px;
        }

        .fw-bold {
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="container">
    <img src="{{ 'data:image/png;base64,' . base64_encode(file_get_contents($brandingData['logo_path'] ?: $brandingData['logo_url'])) }}" style="height: 28px;margin-bottom: 1rem;" />

    <h1>{{ __('order_confirm_title', ['name' => $salesOrder->billingAddress->first_name ?? '']) }}</h1>

    <p>{{ __('order_confirm_text_1') }}</p>

    <p>{{ __('order_confirm_text_2') }}</p>

    <br>

    <br>

    <table class="order-table">
        <tr>
            <th colspan="2">{{ __('order_confirm_items') }}</th>
        </tr>
        @if($salesOrder->lines ?? false)
            @foreach($salesOrder->lines as $salesOrderLine)
                <tr>
                    <td>
                        <div class="item-data">
                            <div class="item-data__image">

                            </div>
                            <div class="item-data__text">
                                <div class="item-data__description">{{ $salesOrderLine->description }}</div>
                                <div>{{ __('order_confirm_quantity') }}: {{ $salesOrderLine->quantity }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div>{{ number_format(($salesOrderLine->unit_price * $salesOrderLine->quantity), 2, '.', ' ') }} {{ $salesOrder->currency }}</div>
                    </td>
                </tr>
            @endforeach
        @endif
        <tr>
            <td colspan="2" class="text-start">
                {{ __('order_confirm_shipping_address') }}:<br>
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
    </table>

    <br><br>

    <div class="box">
        <table>
            <tr>
                <td class="text-start">{{ __('order_confirm_items') }}</td>
                <td class="text-end">2</td>
            </tr>
            <tr>
                <td class="text-start">{{ __('order_confirm_sub_total') }}</td>
                <td class="text-end">{{ number_format($salesOrder->order_total, 2, '.', ' ') }} {{ $salesOrder->currency }}</td>
            </tr>
            <tr class="fw-bold">
                <td class="text-start">{{ __('order_confirm_total') }}</td>
                <td class="text-end">{{ number_format($salesOrder->order_total, 2, '.', ' ') }} {{ $salesOrder->currency }}</td>
            </tr>
        </table>
    </div>

    <br><br>

    <table class="order-table">
        <tr>
            <th colspan="2">{{ __('order_confirm_details') }}</th>
        </tr>
        <tr>
            <td class="text-start">
                {{ __('order_confirm_date') }}: {{ $salesOrder->date }}<br>
                {{ __('order_confirm_number') }}: {{ $salesOrder->order_number }}<br>
                {{ __('order_confirm_pay_method') }}: {{ $salesOrder->pay_method }}<br>
            </td>
        </tr>
        <tr>
            <td class="text-start">
                {{ __('order_confirm_billing_address') }}:<br>
                @if($salesOrder->billingAddress ?? false)
                    {{ $salesOrder->billingAddress->full_name }}<br>
                    {{ $salesOrder->billingAddress->street_line_1 }}<br>
                    @if($salesOrder->billingAddress->street_line_2)
                        {{ $salesOrder->billingAddress->street_line_2 }}<br>
                    @endif
                    {{ $salesOrder->billingAddress->postal_code }} {{ $salesOrder->billingAddress->city }}<br>
                    {{ $salesOrder->billingAddress->country_code }}
                @endif
            </td>
        </tr>
    </table>
</div>

</body>
</html>
