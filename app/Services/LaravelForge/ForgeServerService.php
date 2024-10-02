<?php

namespace App\Services\LaravelForge;

use Illuminate\Support\Facades\Http;

class ForgeServerService extends ForgeApiService
{
    public function getServers()
    {
        $response = $this->callAPI('GET', '/servers');

        return $response['servers'] ?? [];
    }

    public function getMonitors($serverID)
    {
        $response = $this->callAPI('GET', '/servers/' . $serverID . '/monitors');

        return $response['monitors'] ?? [];
    }
}
