<!DOCTYPE html>
<html>
<head>
    <title>Stock Logs</title>

    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
        }
        .container {
            width: 90%;
            max-width: 850px;
            margin: 20px auto;
        }
        input {
            width: 100%;
            font-size: 16px;
            padding: 8px 12px;
            margin-bottom: 4px;
        }
        button {
            width: 100%;
            font-size: 16px;
            height: 38px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th,
        table td {
            border: 1px solid #8e8e8e;
            padding: 6px;
        }

        .text-start {
            text-align: left !important;
        }
        .text-end {
            text-align: right !important;
        }
    </style>
</head>
<body>

<div class="container">

    <h1>Stock logs</h1>

    <form method="GET" action="">
        <input type="text" name="article_number" placeholder="Article number" value="{{ request('article_number') }}"><br>
        <input type="text" name="identifier" placeholder="Stock place identifier" value="{{ request('identifier') }}">
        <button type="submit">Search</button>
    </form>

    @if($stockLogs)
        <br><br>

        <table>
            <tr>
                <th class="text-start">Stock Place</th>
                <th class="text-start">User</th>
                <th class="text-end">Quantity</th>
                <th class="text-end">Source</th>
                <th class="text-end">Time</th>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td class="text-end"><b>{{ $sumQuantity }}</b></td>
                <td></td>
                <td></td>
            </tr>
            @foreach($stockLogs as $stockLog)
                <tr>
                    <td class="text-start">{{ ($stockLog->stockPlaceCompartment->stockPlace->identifier ?? '') . ':' . ($stockLog->stockPlaceCompartment->identifier ?? '') }}</td>
                    <td class="text-start">{{ $stockLog->signature }}</td>
                    <td class="text-end">{{ ($stockLog->quantity > 0) ? ('+' . $stockLog->quantity) : $stockLog->quantity }}</td>
                    <td class="text-start">{{ $stockLog->source }}</td>
                    <td class="text-end">{{ date('Y-m-d H:i:s', strtotime($stockLog->created_at)) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

</div>

</body>
</html>
