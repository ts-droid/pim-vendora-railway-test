<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <title>Purchase Order Price Changes</title>

    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th {
            font-weight: bold;
        }
        table th,
        table td {
            text-align: left;
            border: 1px solid #cccccc;
            padding: 0.5rem 0.5rem;
        }
        .text-right {
            text-align: right;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.25rem;
            border-radius: 0;
            text-decoration: none;
            margin-right: 10px;
        }

        .btn-green {
            background-color: #17A34E;
            color: #ffffff;
        }

        .btn-red {
            background-color: #D73A49;
            color: #ffffff;
        }
    </style>
</head>

<body>
    <p>Price changes have been made by the supplier to the below purchase order.</p>

    <p>
        <b>PO-number:</b> {{ $purchaseOrder->order_number }}<br>
        <b>Supplier:</b> {{ $purchaseOrder->supplier_name }}
    </p>

    <p>The following price changes have been requested:</p>
    <table>
        <tr>
            <th>SKU</th>
            <th class="text-right">Old price</th>
            <th class="text-right">New price</th>
        </tr>
        @if($purchaseOrder->lines)
            @foreach($purchaseOrder->lines as $orderLine)
                @php
                    $changes = null;

                    foreach ($updatedPrices as $update) {
                        if ($update['order_line_id'] == $orderLine->id) {
							$changes = $update;
                            break;
                        }
                    }
                @endphp

                @continue($changes === null)

                <tr>
                    <td>{{ $orderLine->article_number }}</td>
                    <td class="text-right">{{ $changes['from'] }}</td>
                    <td class="text-right">{{ $changes['to'] }}</td>
                </tr>
            @endforeach
        @endif
    </table>

    <p>Please review the changes and accept or reject them.</p>
    <p>If you accept the prices the price list and purchase order will be updated with the new prices.</p>
    <p>If you reject the prices the purchase order will be confirmed with the old prices.</p>
    <p>NOTE! The supplier does not get notified when you accept or reject the prices.</p>

    <a href="{{ route('purchaseOrder.pricesConfirm', ['purchaseOrder' => $purchaseOrder->id, 'hash' => $purchaseOrder->getHash()]) }}" class="btn btn-green">Accept prices</a>
    <a href="{{ route('purchaseOrder.pricesReject', ['purchaseOrder' => $purchaseOrder->id, 'hash' => $purchaseOrder->getHash()]) }}" class="btn btn-red">Reject prices</a>
</body>
</html>
