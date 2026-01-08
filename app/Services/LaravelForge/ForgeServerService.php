<?php

namespace App\Services\LaravelForge;

use Illuminate\Support\Facades\Http;

class ForgeServerService extends ForgeApiService
{
    public function getServers()
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $response = $this->callAPI('GET', '/servers');

        return $response['servers'] ?? [];
    }

    public function getMonitors($serverID)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $response = $this->callAPI('GET', '/servers/' . $serverID . '/monitors');

        return $response['monitors'] ?? [];
    }
}
