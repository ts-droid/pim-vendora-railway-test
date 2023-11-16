<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <title>Purchase Order</title>

    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
        }

        html {
            padding: 0;
            margin: 0;
        }
        body {
            padding: 50px;
            margin: 0;
        }

        .print-a4-holder {
            width: 100%;
        }

        .row {
            width: 100%;
            font-size: 0;
        }

        .col-25,
        .col-33,
        .col-50,
        .col-100 {
            display: inline-block;
            vertical-align: top;
            font-size: 13px;
        }

        .col-25 {
            width: 25%;
        }
        .col-33 {
            width: 33.3%;
        }
        .col-50 {
            width: 50%;
        }
        .col-100 {
            width: 100%;
        }

        .text-center {
            text-align: center;
        }
        .text-end {
            text-align: right;
        }

        h1 {
            margin-top: 0;
        }

        h2 {

        }

        .logo {
            max-height: 30px;
            max-width: 100%;
        }


        table {
            font-size: 12px;
            width: 100%;
            margin: 0;
            padding: 0;
            border-collapse: collapse;
        }
        th, td {
            padding: 3px;
            margin: 0;
            width: 1px;
            text-align: left;
            border: 1px solid #000000;
        }
    </style>
</head>

<body>
    <div class="print-a4-holder">
        <div class="row">
            <div class="col-50">
                <h1>Purchase Order</h1>
            </div>
            <div class="col-50 text-end">
                <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path() . '/assets/img/logo.png')) }}" class="logo">
            </div>
        </div>

        <br><br>

        <div class="row">
            <div class="col-100">
                Hello,<br>
                <br>
                This is a test purchase order text. This text will be replaced by a real purchase order text later.<br>
                This is just for testing purposes.
            </div>
        </div>

        <br><br>

        <div class="row">
            <div class="col-100">
                Please confirm the order by clicking the link below.<br>
                <a href="https://adm.vendora.se/comfirm-purchase-order/[id]/[key]" target="_blank">https://adm.vendora.se/comfirm-purchase-order/[id]/[key]</a>
            </div>
        </div>

        <br><br>

        <table>
            <tr>
                <th>SKU</th>
                <th>Description</th>
                <th class="text-end">Quantity</th>
            </tr>

            @foreach($purchaseOrder->lines as $line)
                <tr>
                    <td>{{ $line->article_number }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="text-end">{{ $line->quantity }}</td>
                </tr>
            @endforeach
        </table>

        <br><br>

        <div class="row">
            <div class="col-100">
                Kind regards,<br>
                Vendora Nordic AB
            </div>
        </div>
    </div>
</body>
</html>
