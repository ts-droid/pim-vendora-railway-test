<!DOCTYPE html>
<html>

<head>
    <title>{{ $title }}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="/resources/demos/style.css">

    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
        }

        body {
            background-color: #F3F3F3;
            padding: 0;
            margin: 0;
        }

        .d-none {
            display: none;
        }

        .mb-0 {
            margin-bottom: 0 !important;
        }

        .text-center {
            text-align: center;
        }

        .container {
            padding: 20px;
            width: 80%;
            max-width: 750px;
            margin: 50px auto;
            background-color: #ffffff;
            box-shadow: 0px 3px 5px #d2d2d2;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo img {
            max-width: 80%;
            max-height: 30px;
        }

        .text-right {
            text-align: right !important;
        }

        .table {
            margin-bottom: 30px;
        }
        .table table {
            width: 100%;
            border-collapse: collapse;
        }
        .table table th,
        .table table td {
            text-align: left;
            border: 1px solid #cccccc;
            padding: 4px;
        }

        .fw-bold {
            font-weight: bold;
        }

        .text {
            text-align: center;
            margin-bottom: 35px;
        }

        .text h5 {
            font-size: 16px;
            margin: 0;
        }

        .text p:last-child {
            margin-bottom: 0;
        }

        .flex-row {
            display: flex;
            align-items: center;
        }

        .d-none {
            display: none !important;
        }

        .me {
            margin-right: 8px;
        }

        .loader {
            width: 15px;
            height: 15px;
            border: 2px solid #FFF;
            border-bottom-color: transparent;
            border-radius: 50%;
            display: inline-block;
            box-sizing: border-box;
            animation: rotation 1s linear infinite;
        }
        @keyframes rotation {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        .button-holder {
            text-align: center;
        }

        .button {
            border: none;
            background-color: #cccccc;
            color: #000000;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 15px;
            opacity: 1;
        }
        .button:hover {
            opacity: 0.95;
        }

        button:disabled,
        button[disabled] {
            opacity: 0.8 !important;
            cursor: not-allowed;
        }

        .button-success {
            background-color: #17A34E;
            color: #ffffff;
        }

        .checkmark-holder {
            margin-bottom: 30px;
        }
        .checkmark-holder .wrapper{
            height: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .checkmark-holder .checkmark__circle{
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke-miterlimit: 10;
            stroke: #17A34E;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards
        }
        .checkmark-holder .checkmark{
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: #fff;
            stroke-miterlimit: 10;
            margin: 10% auto;
            box-shadow: inset 0px 0px 0px #17A34E;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both
        }
        .checkmark-holder .checkmark__check{
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards
        }

        @keyframes stroke{
            100%{
                stroke-dashoffset: 0
            }
        }
        @keyframes scale{
            0%, 100%{
                transform: none
            }
            50%{
                transform: scale3d(1.1, 1.1, 1)
            }
        }
        @keyframes fill{
            100%{
                box-shadow: inset 0px 0px 0px 30px #17A34E
            }
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
</head>

<body>

<div class="container">
    <div class="logo">
        <img src="{{ asset('/assets/img/logo.png') }}">
    </div>

    @yield('content')
</div>

</body>
