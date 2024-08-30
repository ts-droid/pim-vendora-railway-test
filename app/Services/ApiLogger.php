<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ApiLogger
{
    const STORAGE_TIME = 14; // Days

    const TYPE_VISMA = 0;

    public static function log(int $type, string $url, array $params, string $method, array $response): void
    {
        return;

        DB::table('api_logs')->insert([
            'type' => $type,
            'url' => $url,
            'params' => json_encode($params),
            'method' => $method,
            'response' => json_encode($response),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function cleanup(): void
    {
        DB::table('api_logs')
            ->where('created_at', '<', date('Y-m-d H:i:s', strtotime('-' . self::STORAGE_TIME . ' day')))
            ->delete();
    }
}
