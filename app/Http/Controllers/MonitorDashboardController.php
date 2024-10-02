<?php

namespace App\Http\Controllers;

use App\Services\LaravelForge\ForgeServerService;
use Illuminate\Http\Request;

class MonitorDashboardController extends Controller
{
    public function index()
    {
        $forgeServerService = new ForgeServerService();
        $servers = $forgeServerService->getServers();

        foreach ($servers as &$server) {
            $monitorData = [
                'cpu_load' => [],
                'used_memory' => [],
                'disk' => []
            ];

            $monitorStates = [];

            $monitors = $forgeServerService->getMonitors($server['id']);
            foreach ($monitors as $monitor) {
                $monitorData[$monitor['type']][] = $monitor;
            }

            // Sort the monitors by criticality
            foreach ($monitorData as $type => $monitors) {
                if (count($monitors) == 0) {
                    continue;
                }

                usort($monitors, function($a, $b) {
                    return $a['threshold'] <=> $b['threshold'];
                });

                $monitorData[$type] = $monitors;

                $warningState = $monitors[0]['state'];
                $criticalState = $monitors[count($monitors) - 1]['state'];

                if ($criticalState != 'OK') {
                    $monitorStates[$type] = 'WARNING';
                }
                elseif ($warningState != 'OK') {
                    $monitorStates[$type] = 'CRITICAL';
                }
                else {
                    $monitorStates[$type] = 'OK';
                }
            }

            $server['monitors'] = $monitorData;
            $server['monitorStates'] = $monitorStates;
        }

        return view('monitor.index', compact('servers'));
    }

    public static function getStateBadge(string $state)
    {
        if ($state == 'CRITICAL') {
            return '<span class="badge bg-danger">' . $state . '</span>';
        }
        elseif ($state == 'WARNING') {
            return '<span class="badge bg-warning">' . $state . '</span>';
        }
        elseif ($state == 'OK') {
            return '<span class="badge bg-success">' . $state . '</span>';
        }
        else {
            return '';
        }
    }
}
