<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <title>{{ $emailSubject }}</title>

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

    <body>
        {!! $emailBody !!}
    </body>
</html>
