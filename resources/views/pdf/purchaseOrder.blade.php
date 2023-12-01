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
            padding: 0 35px;
            margin: 0;

            font-size: 14px;
        }

        .text-end {
            text-align: right;
        }

        h1 {
            font-size: 25px;
        }

        table {
            width: 100%;
        }

        .header td:nth-child(2) img {
            height: 30px;
        }

        .order-lines {
            text-align: left;
            border-collapse: collapse;
        }
        .order-lines th,
        .order-lines td {
            border: 1px solid #000000;
            padding: 3px;
        }
    </style>
</head>
<body>

<table class="header">
    <tr>
        <td>
            <h1>Purchase Order</h1>
        </td>
        <td class="text-end">
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path() . '/assets/img/logo.png')) }}" class="logo">
        </td>
    </tr>
</table>

<table class="text">
    <tr>
        <td>
            Hello,<br>
            <br>
            This is a test purchase order text. This text will be replaced by a real purchase order text later.<br>
            This is just for testing purposes.
        </td>
    </tr>
</table>

<br><br>

<table class="order-lines">
    <tr>
        <th>SKU</th>
        <th>Description</th>
        <th>Unit price</th>
        <th class="text-end">Quantity</th>
    </tr>
    @for($i = 0;$i < 8;$i++)
        <tr>
            <td>DMD-SSK-194</td>
            <td>This is the article name</td>
            <td>{{ rand(10, 10000) / 10 }}</td>
            <td class="text-end">{{ rand(1, 500) }}</td>
        </tr>
    @endfor
</table>

</body>
</html>
