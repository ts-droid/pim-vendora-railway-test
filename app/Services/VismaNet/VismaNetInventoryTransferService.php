<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ConfigController;

class VismaNetInventoryTransferService extends VismaNetApiService
{
    public function fetchInventoryTransfers(string $updatedAfter = ''): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_inventory_transfer_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = date('Y-m-d H:i:s', strtotime('-10 minutes', strtotime($updatedAfter)));
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $transfers = $this->getPagedResult('/v1/inventoryTransfer', $params);

        if ($transfers) {
            foreach ($transfers as $transfer) {
                if (!$transfer || !is_array($transfer)) {
                    continue;
                }

                $fetchedData = true;

                $this->importTransfer($transfer);
            }
        }

        if ($fetchedData) {
            ConfigController::setConfigs(['vismanet_last_inventory_transfer_fetch' => $fetchTime]);
        }
    }

    public function importTransfer(array $transfer): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        foreach ($transfer['transferLines'] as $line) {
            $articleNumber = $line['inventoryItem']['number'] ?? '';

            trigger_stock_sync($articleNumber);
        }
    }
}
