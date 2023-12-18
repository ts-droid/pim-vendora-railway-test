<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <title>Purchase Order Reminder</title>

    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
        }
    </style>

    <body>
        Hello,<br><br>

        We have noticed that we have not yet received the following items that we ordered from you on {{ $purchaseOrder->date }}.<br><br>

        @if($orderLines)
            @foreach($orderLines as $orderLine)
                {{ $orderLine->description }} - {{ $orderLine->quantity }} pcs<br>
            @endforeach
        @endif

        Could you please let us know when we can expect to receive these items?<br><br>

        Kind regards,<br>
        Vendora Nordic AB
    </body>
</html>
