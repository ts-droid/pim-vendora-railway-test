<!DOCTYPE html>
<html>

<head>
    <title>{{ __('request_review_done_title') }}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 1rem;
        }
        body {
            background-color: #F8F8F8;
        }

        .container {
            width: 100%;
            max-width: 450px;
            margin: 35px auto;
        }

        .title {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .title .icon {
            margin-right: 10px;
        }
        .title .text {
            font-size: 18px;
            margin-bottom: 2px;
        }

        p {
            text-align: center;
        }

    </style>
</head>

<body>

<div class="container">
    <div class="title">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
            </svg>
        </div>
        <div class="text">{{ __('request_review_done_heading') }}</div>
    </div>

    <p>{{ __('request_review_done_text') }}</p>
</div>

</body>
