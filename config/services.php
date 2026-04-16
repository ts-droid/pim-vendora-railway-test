<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'tilde' => [
        'api_url' => env('TILDE_API_URL', ''),
        'api_key' => env('TILDE_API_KEY', ''),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'deepl' => [
        'api_key' => env('DEEPL_API_KEY', '')
    ],

    'openai' => [
        'key' => env('OPEN_AI_KEY', ''),
        'endpoint' => env('OPEN_AI_ENDPOINT', ''),
    ],

    'claude' => [
        'key' => env('CLAUDE_KEY', ''),
        'endpoint' => env('CLAUDE_ENDPOINT', ''),
    ],

    'gs1' => [
        'api_key' => env('GS1_API_KEY', ''),
        'company_prefix' => env('GS1_COMPANY_PREFIX', ''),
        'generate_url' => env('GS1_GENERATE_URL', 'https://services.validoo.se/licence.api/licences/key/generate'),
        'activate_url' => env('GS1_ACTIVATE_URL', 'https://services.validoo.se/tradeitem.api/activate/gtins'),
        'default_brand' => env('GS1_DEFAULT_BRAND', 'BUNDLE'),
        'country_code' => env('GS1_COUNTRY_CODE', '752'),
    ],

    'mailerlite' => [
        'domains' => [
            'vendora.se',
            'alogic.se',
            'clickandgrow.se',
            'ecocordz.com',
            'herqs.se',
            'just-mobile.se',
            'keybudz.se',
            'nordicsmartlight.se',
            'mujjo.se',
            'myfirst.se',
            'paperlike.se',
            'pipetto.se',
            'plaud.se',
            'playshifu.se',
            'satechi.se',
            'stufflet.se',
            'twelvesouth.se',
            'woox.nu',
        ]
    ]

];
