<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ConfigController;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentLine;

class VismaNetInventoryAdjustmentService extends VismaNetApiService
{
    public function fetchInventoryAdjustments(string $updatedAfter = ''): void
    {
        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_inventory_adjustment_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = date('Y-m-d H:i:s', strtotime('-10 minutes', strtotime($updatedAfter)));
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $adjustments = $this->getPagedResult('/v1/inventoryadjustment', $params);

        if ($adjustments) {
            foreach ($adjustments as $adjustment) {
                if (!$adjustment || !is_array($adjustment)) {
                    continue;
                }

                $adjustment = true;

                $this->importAdjustment($adjustment);
            }
        }

        if ($fetchedData) {
            ConfigController::setConfigs(['vismanet_last_inventory_adjustment_fetch' => $fetchTime]);
        }
    }

    public function importAdjustment(array $adjustment): void
    {
        $data = [
            'reference_number' => (string) ($adjustment['referenceNumber'] ?? ''),
            'status' => (string) ($adjustment['status'] ?? ''),
            'date' => date('Y-m-d', strtotime($adjustment['date'] ?? '')),
            'total_cost' => (float) ($adjustment['totalCost'] ?? 0),
            'control_cost' => (float) ($adjustment['controlCost'] ?? 0),
        ];

        $adjustmentModel = InventoryAdjustment::where('reference_number', $data['reference_number'])->first();

        if ($adjustmentModel) {
            $adjustmentModel->update($data);
        }
        else {
            $adjustmentModel = InventoryAdjustment::create($data);
        }

        InventoryAdjustmentLine::where('inventory_adjustment_id', $adjustmentModel->id)->delete();

        foreach ($adjustment['adjusmentLines'] as $line) {
            $articleNumber = (string) ($line['inventoryItem']['number'] ?? '');

            $lineData = [
                'inventory_adjustment_id' => $adjustmentModel->id,
                'line_number' => (int) $line['lineNumber'],
                'article_number' => $articleNumber,
                'quantity' => (int) ($line['quantity'] ?? 0),
                'unit_cost' => (float) ($line['unitCost'] ?? 0),
                'ext_cost' => (float) ($line['extCost'] ?? 0),
                'reason_code' => (string) ($line['reasonCode']['id'] ?? ''),
                'description' => (string) ($line['description'] ?? ''),
            ];

            InventoryAdjustmentLine::create($lineData);

            // trigger_stock_sync($articleNumber);
        }
    }
}
