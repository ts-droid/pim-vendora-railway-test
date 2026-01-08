<?php

namespace App\Services\VendoraAdmin;

class VendoraAdminTaskService extends VendoraAdminService
{
    public function createTask(string $taskType, array $data): int
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $postData = $data;
        $postData['type'] = $taskType;

        $response = $this->callAPI('POST', '/tasks', $postData);

        return (int) ($response['data']['task']['id'] ?? -1);
    }
}
