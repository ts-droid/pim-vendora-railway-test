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
        // Legacy — kept for any callers that still read it.
        'api_key' => env('GS1_API_KEY', ''),

        'company_prefix' => env('GS1_COMPANY_PREFIX', ''),
        'generate_url' => env('GS1_GENERATE_URL', 'https://services.validoo.se/licence.api/licences/key/generate'),
        'activate_url' => env('GS1_ACTIVATE_URL', 'https://services.validoo.se/tradeitem.api/activate/gtins'),
        'default_brand' => env('GS1_DEFAULT_BRAND', 'BUNDLE'),
        'country_code' => env('GS1_COUNTRY_CODE', '752'),

        // OAuth2 password-grant credentials from MyGS1 Technical
        // Integration page. Scope must include the APIs we call +
        // "offline_access" to get a refresh token.
        'token_url' => env('GS1_TOKEN_URL', 'https://validoopwe-apimanagement.azure-api.net/connect/token'),
        'client_id' => env('GS1_CLIENT_ID', ''),
        'client_secret' => env('GS1_CLIENT_SECRET', ''),
        'username' => env('GS1_USERNAME', ''),
        'password' => env('GS1_PASSWORD', ''),
        'scope' => env('GS1_SCOPE', 'numberseries tradeitem offline_access'),
        'environment' => env('GS1_ENVIRONMENT', 'Production'),
    ],

    'vendora_crm' => [
        // URL template that resolves to a customer page in Vendora CRM.
        // {vat} placeholder is replaced with the customer's vat_number.
        // Example: 'https://vendora-crm.example.com/customers?vat={vat}'
        // Default: the CRM instance we maintain at ts-droid/CRM on Railway.
        'customer_url_template' => env('VENDORA_CRM_CUSTOMER_URL', 'https://empathetic-empathy-production.up.railway.app/?vat={vat}'),
        // If the CRM's content-security-policy blocks iframe embedding,
        // flip this to false to render a "open in new tab" button instead.
        'embed_in_iframe' => filter_var(env('VENDORA_CRM_IFRAME', true), FILTER_VALIDATE_BOOL),
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
