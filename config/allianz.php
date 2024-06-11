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

];
