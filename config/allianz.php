<?php

return [

    'production' => (bool) env('ALLIANZ_PRODUCTION', false),

    'api_key' => env('ALLIANZ_API_KEY', ''),

    'contracts' => [
        'contract_1' => [
            'code' => 'EHSE',
            'policy_id' => '035659',
            'extension' => '',
        ],
        'contract_2' => [
            'code' => 'EHSE',
            'policy_id' => '035748',
            'extension' => 'DCL',
        ],
        'contract_3' => [
            'code' => 'EHSE',
            'policy_id' => '035749',
            'extension' => 'CAP',
        ],
    ],

    'endpoints' => [
        'oauth_token' => [
            'test' => 'https://api-services.uat.1placedessaisons.com/uatm/v1/idp/oauth2/authorize',
            'prod' => 'https://api.allianz-trade.com/v1/idp/oauth2/authorize',
        ],

        'riskinfo_cover_search' => [
            'test' => 'https://api-services.uat.1placedessaisons.com/uatm/riskinfo/v3/covers/search',
            'prod' => 'https://api.allianz-trade.com/riskinfo/v3/covers/search',
        ],

        'find_companies' => [
            'test' => 'https://api-services.uat.1placedessaisons.com/company-referential/uatm-v1/companies/list',
            'prod' => 'https://api.allianz-trade.com/company-referential/v1/companies/list',
        ],
    ],

];
