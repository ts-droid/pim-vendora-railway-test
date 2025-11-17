<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <title>Discrepancy in shipment: {{ $purchaseOrderShipment->id }}</title>

    <style>
        *  {
            font-family: Arial, Helvetica, sans-serif;
        }
    </style>

</head>

<body>

<p>Hello,</p>

<p>In shipment #{{ $purchaseOrderShipment->id }} related to purchase order #{{ $purchaseOrderShipment->purchaseOrder->id }} we recieved the following items that does not match our records what what was expected.</p>
<p>Could you please supply us with information regarding this deviation.</p>

@foreach($exceptions as $exception)
    <p>
        <b>Item:</b> {{ ($exception->line->article_number ?? null) ?: $exception->article_number ?: '' }}<br>
        <b>Quantity:</b> {{ $exception->diff > 0 ? ('+' . $exception->diff) : $exception->diff }}<br>
        <b>Reason:</b> {{ $exception->exception_type }}
    </p>
@endforeach

</body>
</html>
