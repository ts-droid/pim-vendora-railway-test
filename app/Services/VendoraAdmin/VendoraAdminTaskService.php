<?php

namespace App\Services\VendoraAdmin;

class VendoraAdminTaskService extends VendoraAdminService
{
    public function createTask(string $taskType, array $data): int
    {
        $postData = $data;
        $postData['type'] = $taskType;

        $response = $this->callAPI('POST', '/tasks', $postData);

        dd($response);

        return (int) ($response['data']['task']['id'] ?? -1);
    }
}
