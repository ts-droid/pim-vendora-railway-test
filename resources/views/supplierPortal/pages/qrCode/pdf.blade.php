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
        }
    </style>
</head>
<body>
<div class="qrcode">
    <img src="data:image/png;base64,{!! base64_encode($qrCode) !!}" />
</div>

</body>
</html>
