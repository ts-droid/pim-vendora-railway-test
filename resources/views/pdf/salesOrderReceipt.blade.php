<!DOCTYPE html>
<html>
    <head>
        <style>
            body {
                font-family: Arial, Helvetica, sans-serif;
                font-size: 13px;
            }
            table {
                width: 100%;
                table-layout: fixed;
                border-collapse: collapse;
            }
            td {
                vertical-align: top;
                padding: 0;
            }

            .title {
                font-size: 18px;
                font-weight: bold;
            }

            .bold {
                font-weight: bold;
            }

            .small {
                font-size: 12px;
            }
            .text-start {
                text-align: left !important;
            }
            .text-end {
                text-align: right !important;
            }

            .line {
                width: 100%;
                height: 1px;
                background-color: #000000;
                margin: 6px 0;
            }
        </style>
    </head>
    <body>

    <table>
        <tr>
            <td width="50%">
                <div>
                    <img src="{{ get_image_base_64($brandingData['logo_path'] ?: $brandingData['logo_url']) }}" style="height: 25px" />
                </div>
                @if($brandingData['brand_name'] != 'Vendora Nordic AB')
                    <br>
                    <div class="small"><b>{{ __('through') }} {{ __('company_name') }}</b></div>
                @endif
            </td>
            <td width="50%">
                <div class="title">
                    {{ __('receipt_title_2') }}
                </div>
                <table>
                    <tr class="bold">
                        <td width="50%">{{ __('receipt_order_nbr') }}:</td>
                        <td width="50%" class="text-end">
                            @if($shipment->order_numbers && is_array($shipment->order_numbers))
                                {{ implode(', ', $shipment->order_numbers) }}
                            @endif
                        </td>
                    </tr>
                    <tr class="bold">
                        <td width="50%">{{ __('receipt_date') }}:</td>
                        <td width="50%" class="text-end">{{ date('Y-m-d') }}</td>
                    </tr>
                    <tr class="bold">
                        <td width="50%">{{ __('receipt_customer_nbr') }}:</td>
                        <td width="50%" class="text-end">
                            @if(is_web_customer((string) $shipment->customer_number))
                                {{ __('web_customer') }}
                            @else
                                {{ $shipment->customer_number }}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="line"></div>

    <br>

    <table>
        <tr>
            <td width="50%">
                <div class="bold">{{ __('receipt_shipping_from') }}:</div>
                {{ __('company_name') }}<br>
                {{ __('company_address') }}<br>
                {{ __('company_zip') }} {{ __('company_city') }}<br>
                {{ __('company_contact') }}<br>
                {{ __('company_phone') }}

                <br><br>

                <div class="bold">{{ __('receipt_delivery_address') }}:</div>
                @if($shipment->name)
                    {{ $shipment->name }}<br>
                @else
                    {{ $shipment->address->full_name ?? '' }}<br>
                @endif
                @if($shipment->attention)
                    ATT: {{ $shipment->attention }}<br>
                @endif
                {{ $shipment->address->street_line_1 ?? '' }}<br>
                {{ $shipment->address->postal_code ?? '' }} {{ $shipment->address->city ?? '' }}<br>
                {{ get_country_name($shipment->address->country_code ?? '', \Illuminate\Support\Facades\App::getLocale()) }}
            </td>

            <td width="50%">
                <table>
                    <tr class="bold">
                        <td width="50%">{{ __('receipt_delivery_date') }}:</td>
                        <td width="50%" class="text-end">{{ $shipment->date }}</td>
                    </tr>
                </table>

                <br>
                <br>

                @if($shipment->salesOrder() && $shipment->salesOrder()->vat_number)
                    {{ __('receipt_vat_number') }}: {{ $shipment->salesOrder()->vat_number }}<br>
                @endif
                {{ __('receipt_customer_order_nbr') }}: <br>
                {{ __('receipt_fob') }}: <br>
                {{ __('receipt_shipping_terms') }}: <br>
                {{ __('receipt_shipping_method') }}:
            </td>
        </tr>
    </table>

    <br><br><br>

    <table>
        <tr>
            <th class="text-start">{{ __('receipt_line_pos') }}</th>
            <th class="text-start">{{ __('receipt_line_sku') }}</th>
            <th class="text-start">{{ __('receipt_line_description') }}</th>
            <th class="text-end">{{ __('receipt_line_qty_ordered') }}</th>
            <th class="text-end">{{ __('receipt_line_qty_shipped') }}</th>
            <th class="text-end">{{ __('receipt_line_batch') }}</th>
        </tr>
        @if($shipment->lines)
            @foreach($shipment->lines as $line)
                <tr>
                    <td class="text-start">{{ $line->line_number }}</td>
                    <td class="text-start">{{ $line->article_number }}</td>
                    <td class="text-start">{{ $line->description }}</td>
                    <td class="text-end">{{ $line->orderQuantity() }}</td>
                    <td class="text-end">{{ $line->picked_quantity }}</td>
                    <td class="text-end">
                        @if($line->serial_number)
                            {{ $line->serial_number }}
                        @endif
                    </td>
                </tr>
            @endforeach
        @endif
    </table>

    <br><br><br>

    <table>
        <tr>
            <td width="33.33%"></td>
            <td width="33.33%"></td>
            <td width="33.33%">
                <table>
                    <tr>
                        <td width="50%">{{ __('receipt_total_quantity') }}:</td>
                        <td width="50%" class="text-end">{{ $shipment->calculateTotalQuantity() }}</td>
                    </tr>
                    <tr>x
                        <td width="50%">{{ __('receipt_total_weight') }}:</td>
                        <td width="50%" class="text-end">{{ round($shipment->calculateTotalWeight() / 1000, 2) }} KG</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <br><br>

    <div class="line"></div>

    <table>
        <tr>
            <td width="40%">
                {{ __('company_name') }}<br>
                {{ __('company_address') }}<br>
                {{ __('company_zip') }} {{ __('company_city') }}<br>
                {{ __('company_country') }}<br>
                {{ __('company_phone') }}<br>
                {{ __('company_email') }}<br>
                {{ __('company_headquarter') }}
            </td>
            <td width="40%">
                {{ __('receipt_org_nr') }}: {{ __('company_org_nr') }}<br>
                {{ __('receipt_vat_nr') }}: {{ __('company_vat_nr') }}<br>
                {{ __('receipt_vat_type') }}: {{ __('company_vat_type') }}
            </td>
            <td width="20%"></td>
        </tr>
    </table>


    </body>
</html>
