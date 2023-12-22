<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <title>New Purchase Order {{ $purchaseOrder->order_number }} - Vendora Nordic AB</title>

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
    </style>
</head>

<body>
    Dear,<br><br>

    Attached is our new purchase order {{ $purchaseOrder->order_number }}, which should be consolidated with any existing backorders.<br><br>

    Order no: {{ $purchaseOrder->order_number }}<br>
    Order date: {{ $purchaseOrder->date }}<br>
    Terms of payment: -----<br>

    <br>

    @if($purchaseOrder->lines)
        <table>
            <tr>
                <th>SKU</th>
                <th>Description</th>
                <th style="text-align: right;">Quantity</th>
                <th style="text-align: right;">Unit price</th>
                <th style="text-align: right;">Total</th>
            </tr>
            @foreach($purchaseOrder->lines as $orderLine)
                <tr>
                    <td>{{ $orderLine->article_number }}</td>
                    <td>{{ $orderLine->description }}</td>
                    <td style="text-align: right;">{{ $orderLine->quantity }}</td>
                    <td style="text-align: right;">{{ $orderLine->unit_cost }}</td>
                    <td style="text-align: right;">{{ ($orderLine->unit_cost * $orderLine->quantity) }}</td>
                </tr>
            @endforeach
        </table>
        <br>
    @endif

    Within 2 (two) days of receiving this PO, please confirm the order using the link below.<br><br>

    <a href="{{ route('purchaseOrder.confirm', ['purchaseOrder' => $purchaseOrder->id, 'hash' => $purchaseOrder->getHash()]) }}" target="_blank">Confirm the order here</a><br><br>

    As soon as the shipment is picked up, a tracking number must be provided to logistics@vendora.se<br><br>

    For shipments containing mixed SKUs in the same box, clearly indicate this on the exterior with a sticker or packing tape.<br><br>

    Please ensure all shipments are directed to the specified delivery address for Vendora Nordic AB, as detailed below.<br><br>

    Delivery address:<br>
    Vendora Nordic AB<br>
    Ladugårdsvägen 1<br>
    234 35 Lomma<br>
    Sweden
</body>
</html>
