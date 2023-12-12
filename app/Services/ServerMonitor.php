<?php

namespace App\Services;

use App\Http\Controllers\StatusIndicatorController;
use App\Services\LaravelForge\ForgeServerService;

class ServerMonitor
{
    public function monitor()
    {
        $forgeServerService = new ForgeServerService();
        $servers = $forgeServerService->getServers();

        foreach ($servers as $server) {
            if ($this->isServerUp($server['ip_address'], $server['type'])) {
                // Server is up, ping status indicator
                StatusIndicatorController::ping($server['name'], 900); // 15 minutes
            }
            else {
                // Server is down, do not ping
            }
        }
    }

    /**
     * Returns true if the server is up, false if it is down
     *
     * @param string $ip
     * @param string $serverType
     * @return bool
     */
    private function isServerUp(string $ip, string $serverType) {


        $port = $this->getServerPort($serverType);

        $timeout = 2; // Timeout in seconds

        $fp = @fsockopen($ip, $port, $errno, $errstr, $timeout);

        if (!$fp) {
            return false; // Server is down
        } else {
            fclose($fp);
            return true; // Server is up
        }
    }

    /**
     * Returns the port to use for the given server type
     *
     * @param string $serverType
     * @return int
     */
    private function getServerPort(string $serverType): int
    {
        switch ($serverType) {
            case 'worker':
            case 'database':
            case 'cache':
                return 22;

            case 'app':
            case 'web':
            case 'loadbalancer':
            case 'app':
            default:
                return 80;
        }
    }
}
