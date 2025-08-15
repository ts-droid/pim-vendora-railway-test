<!DOCTYPE html>
<html>
<head>
    <title>QR Code</title>
    <style>
        body {
            text-align: center;
            font-family: Arial, sans-serif;
        }
        .qrcode {
            margin-top: 50px;
            margin-bottom: 35px;
        }

        .meta-data {
            font-size: 22px;
        }
        .qr-type {
            font-size: 46px;
            margin-top: 25px;
        }
    </style>
</head>
<body>
<div class="qrcode">
    <img src="data:image/png;base64,{!! base64_encode($qrCode) !!}" />
</div>
@if($metaData)
    @foreach($metaData as $key => $value)
        <div class="meta-data"><b>{{ $key }}:</b> {{ $value }}</div>
    @endforeach
@endif
<div class="qr-type">{{ $qrType }}</div>

</body>
</html>
