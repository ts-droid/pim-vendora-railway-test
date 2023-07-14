<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    /**
     * Returns true if the given API key is valid.
     *
     * @param string $apiKey
     * @return bool
     */
    public function validateKey(string $apiKey): bool
    {
        return ApiKey::where('api_key', $apiKey)
            ->where(function($query) {
                $query->where('expires_at', '>', date('Y-m-d H:i:s'))
                    ->orWhereNull('expires_at');
            })
            ->exists();
    }

    /**
     * Generates, stores and returns a new API key.
     *
     * @param string $expiresAt
     * @return string
     */
    public function generate(string $expiresAt = ''): string
    {
        $key = implode('-', str_split(substr(strtolower(md5(microtime().rand(1000, 9999))), 0, 30), 6));

        ApiKey::create([
            'api_key' => $key,
            'expires_at' => $expiresAt ?: null,
        ]);

        return $key;
    }
}
