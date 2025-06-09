<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ConfigController;

class VismaNetInventoryIssueService extends VismaNetApiService
{
    public function fetchInventoryIssues(string $updatedAfter = ''): void
    {
        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_inventory_issue_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = date('Y-m-d H:i:s', strtotime('-10 minutes', strtotime($updatedAfter)));
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $issues = $this->getPagedResult('/v1/inventoryissue', $params);

        if ($issues) {
            foreach ($issues as $issue) {
                if (!$issue || !is_array($issue)) {
                    continue;
                }

                $fetchedData = true;

                $this->importIssue($issue);
            }
        }

        if ($fetchedData) {
            ConfigController::setConfigs(['vismanet_last_inventory_issue_fetch' => $fetchTime]);
        }
    }

    public function importIssue(array $issue): void
    {
        foreach ($issue['issueLines'] as $line) {
            $articleNumber = $line['inventoryItem']['number'] ?? '';

            trigger_stock_sync($articleNumber);
        }
    }
}
